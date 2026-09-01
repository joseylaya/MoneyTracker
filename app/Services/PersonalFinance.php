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
    public function allocateIncome(User $user, int $amount): void {
        $settings=$this->settings($user);
        foreach(['savings'=>'savings_percent','emergency'=>'emergency_percent'] as $bucket=>$field){$allocation=(int)round($amount*((float)$settings->$field/100));if($allocation)PersonalBucket::firstOrCreate(['user_id'=>$user->id,'type'=>$bucket])->increment('reserved_minor',$allocation);}
    }
    public function salaryCycle(string $date, string $period): Carbon {
        $incomeDate=Carbon::parse($date);
        return $period==='first' ? $incomeDate->copy()->day(min(15,$incomeDate->daysInMonth))->startOfDay() : $incomeDate->copy()->addMonthNoOverflow()->startOfMonth();
    }
    public function salaryBillReserve(User $user, Carbon $cycle, int $incomeAmount): int {
        $cycleMonth=$cycle->copy()->startOfMonth(); $isFirstCycle=$cycle->day===15;
        $paidAmounts=PersonalTransaction::where('user_id',$user->id)->where('type','expense')->whereNotNull('personal_commitment_id')->whereBetween('occurred_on',[$cycleMonth->copy()->startOfMonth(),$cycleMonth->copy()->endOfMonth()])->selectRaw('personal_commitment_id, sum(amount_minor) as total')->groupBy('personal_commitment_id')->pluck('total','personal_commitment_id');
        $bills=PersonalCommitment::where('user_id',$user->id)->where('active',true)->get();
        $needed=(int)$bills->filter(fn($bill)=>$isFirstCycle ? $bill->due_day>=15 : $bill->due_day<15)->sum(fn($bill)=>max(0,$bill->amount_minor-(int)($paidAmounts[$bill->id]??0)));
        $alreadyReserved=(int)PersonalTransaction::where('user_id',$user->id)->where('type','income')->whereDate('bill_cycle_date',$cycle->toDateString())->sum('bill_reserved_minor');
        return min($incomeAmount,max(0,$needed-$alreadyReserved));
    }
    public function summary(User $user): array {
        $settings=$this->settings($user); PersonalAccount::firstOrCreate(['user_id'=>$user->id,'name'=>'Cash'],['type'=>'cash','balance_minor'=>0,'last_reconciled_at'=>now()]); $today=now(); $accounts=PersonalAccount::where('user_id',$user->id)->orderBy('name')->get();
        $buckets=PersonalBucket::where('user_id',$user->id)->get()->keyBy('type'); $liquid=(int)$accounts->sum('balance_minor');
        $incomeTransactions=PersonalTransaction::where('user_id',$user->id)->where('type','income')->whereBetween('occurred_on',[$today->copy()->startOfMonth(),$today->copy()->endOfMonth()])->get(['amount_minor','bill_reserved_minor']);
        $income=(int)$incomeTransactions->sum('amount_minor'); $planningIncome=(int)$incomeTransactions->sum(fn($transaction)=>(int)$transaction->amount_minor-(int)($transaction->bill_reserved_minor??0));
        $lifestyle=(int)PersonalTransaction::where('user_id',$user->id)->where('type','expense')->where('category','Lifestyle')->whereNull('personal_commitment_id')->whereBetween('occurred_on',[$today->copy()->startOfMonth(),$today->copy()->endOfMonth()])->sum('amount_minor');
        $lifestyleBudget=(int)round($planningIncome*((float)$settings->lifestyle_percent/100));
        $paidAmounts=PersonalTransaction::where('user_id',$user->id)->where('type','expense')->whereNotNull('personal_commitment_id')->whereBetween('occurred_on',[$today->copy()->startOfMonth(),$today->copy()->endOfMonth()])->selectRaw('personal_commitment_id, sum(amount_minor) as total')->groupBy('personal_commitment_id')->pluck('total','personal_commitment_id');
        $commitments=PersonalCommitment::where('user_id',$user->id)->where('active',true)->orderBy('due_day')->get()->map(function($bill) use($paidAmounts,$today) { $bill->paid_minor=(int)($paidAmounts[$bill->id]??0); $bill->remaining_minor=max(0,$bill->amount_minor-$bill->paid_minor); $bill->status=$bill->remaining_minor===0?'Paid':($bill->due_day<$today->day?'Overdue':($bill->paid_minor>0?'Partially paid':'Reserved')); return $bill; });
        $periodStart=$today->day<=15?1:16; $periodEnd=$today->day<=15?15:$today->daysInMonth; $upcomingCommitments=$commitments->filter(fn($bill)=>$bill->status==='Overdue'||($bill->due_day>=$periodStart&&$bill->due_day<=$periodEnd))->sortBy(fn($bill)=>[$bill->status==='Overdue'?0:1,$bill->due_day])->values();
        $cycleNeeded=$commitments->where('remaining_minor','>',0)->groupBy(fn($bill)=>$bill->due_day>=15?$today->copy()->startOfMonth()->day(min(15,$today->daysInMonth))->toDateString():$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString())->map(fn($bills)=>(int)$bills->sum('remaining_minor'));
        $cycleReserved=PersonalTransaction::where('user_id',$user->id)->where('type','income')->whereNotNull('bill_cycle_date')->get(['bill_cycle_date','bill_reserved_minor'])->groupBy(fn($transaction)=>Carbon::parse($transaction->bill_cycle_date)->toDateString())->map(fn($transactions)=>(int)$transactions->sum('bill_reserved_minor'));
        $reservedBills=(int)$cycleNeeded->sum(fn($needed,$cycle)=>min($needed,(int)($cycleReserved[$cycle]??0))); $billShortfall=max(0,(int)$cycleNeeded->sum()-$reservedBills); $savings=(int)($buckets['savings']->reserved_minor ?? 0); $emergency=(int)($buckets['emergency']->reserved_minor ?? 0);
        $safe=max(0,$liquid-$reservedBills-$savings-$emergency); $lifestyleAvailable=min(max(0,$lifestyleBudget-$lifestyle),$safe); $nextPayday=null; foreach(array_unique(array_filter([$settings->payday_day,$settings->second_payday_day])) as $paydayDay){$candidate=$today->copy()->day(min((int)$paydayDay,$today->daysInMonth));if($candidate->lte($today))$candidate->addMonthNoOverflow();if($nextPayday===null||$candidate->lt($nextPayday))$nextPayday=$candidate;} $nextPayday ??= $today->copy()->addMonthNoOverflow(); $days=max(1,$today->diffInDays($nextPayday));
        return compact('settings','accounts','buckets','liquid','income','planningIncome','lifestyle','lifestyleBudget','lifestyleAvailable','commitments','upcomingCommitments','reservedBills','billShortfall','savings','emergency','safe','nextPayday','days');
    }
}
