<?php

namespace App\Services;

use App\Models\PersonalAccount;
use App\Models\PersonalBucket;
use App\Models\PersonalCommitment;
use App\Models\PersonalFinanceSetting;
use App\Models\PersonalTransaction;
use App\Models\User;
use Carbon\Carbon;

class PersonalFinance
{
    public function settings(User $user): PersonalFinanceSetting { return PersonalFinanceSetting::firstOrCreate(['user_id' => $user->id]); }
    public function summary(User $user): array {
        $settings=$this->settings($user); PersonalAccount::firstOrCreate(['user_id'=>$user->id,'name'=>'Cash'],['type'=>'cash','balance_minor'=>0,'last_reconciled_at'=>now()]); $today=now(); $accounts=PersonalAccount::where('user_id',$user->id)->orderBy('name')->get();
        $buckets=PersonalBucket::where('user_id',$user->id)->get()->keyBy('type'); $liquid=(int)$accounts->sum('balance_minor');
        $income=(int)PersonalTransaction::where('user_id',$user->id)->where('type','income')->whereBetween('occurred_on',[$today->copy()->startOfMonth(),$today->copy()->endOfMonth()])->sum('amount_minor');
        $lifestyle=(int)PersonalTransaction::where('user_id',$user->id)->where('type','expense')->where('category','Lifestyle')->whereBetween('occurred_on',[$today->copy()->startOfMonth(),$today->copy()->endOfMonth()])->sum('amount_minor');
        $lifestyleBudget=(int)round($income*((float)$settings->lifestyle_percent/100));
        $paidAmounts=PersonalTransaction::where('user_id',$user->id)->where('type','expense')->whereNotNull('personal_commitment_id')->whereBetween('occurred_on',[$today->copy()->startOfMonth(),$today->copy()->endOfMonth()])->selectRaw('personal_commitment_id, sum(amount_minor) as total')->groupBy('personal_commitment_id')->pluck('total','personal_commitment_id');
        $commitments=PersonalCommitment::where('user_id',$user->id)->where('active',true)->orderBy('due_day')->get()->map(function($bill) use($paidAmounts,$today) { $bill->paid_minor=(int)($paidAmounts[$bill->id]??0); $bill->remaining_minor=max(0,$bill->amount_minor-$bill->paid_minor); $bill->status=$bill->remaining_minor===0?'Paid':($bill->due_day<$today->day?'Overdue':($bill->paid_minor>0?'Partially paid':'Reserved')); return $bill; });
        $periodStart=$today->day<=15?1:16; $periodEnd=$today->day<=15?15:$today->daysInMonth; $upcomingCommitments=$commitments->filter(fn($bill)=>$bill->status==='Overdue'||($bill->due_day>=$periodStart&&$bill->due_day<=$periodEnd))->sortBy(fn($bill)=>[$bill->status==='Overdue'?0:1,$bill->due_day])->values();
        $reservedBills=(int)$commitments->sum('remaining_minor'); $savings=(int)($buckets['savings']->reserved_minor ?? 0); $emergency=(int)($buckets['emergency']->reserved_minor ?? 0);
        $safe=max(0,$liquid-$reservedBills-$savings-$emergency); $nextPayday=null; foreach(array_unique(array_filter([$settings->payday_day,$settings->second_payday_day])) as $paydayDay){$candidate=$today->copy()->day(min((int)$paydayDay,$today->daysInMonth));if($candidate->lte($today))$candidate->addMonthNoOverflow();if($nextPayday===null||$candidate->lt($nextPayday))$nextPayday=$candidate;} $nextPayday ??= $today->copy()->addMonthNoOverflow(); $days=max(1,$today->diffInDays($nextPayday));
        return compact('settings','accounts','buckets','liquid','income','lifestyle','lifestyleBudget','commitments','upcomingCommitments','reservedBills','savings','emergency','safe','nextPayday','days');
    }
}
