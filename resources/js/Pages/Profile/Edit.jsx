import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Landmark, LogOut } from 'lucide-react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import PushNotifications from '@/Components/PushNotifications';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout
            header={
                <div><h2 className="font-display text-2xl font-bold leading-tight">Profile</h2><p className="mt-1 text-sm text-slate-500">Manage your SplitShare account.</p></div>
            }
        >
            <Head title="Profile" />

            <div className="page-wrap max-w-3xl">
                <div className="space-y-5">
                    <div className="ss-card p-6 sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className="ss-card p-6 sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>
                    <PushNotifications />

                    <div className="ss-card flex flex-wrap items-center justify-between gap-4 p-6 sm:p-7">
                        <div><h2 className="font-display text-xl font-bold">Personal accounts</h2><p className="mt-1 text-sm text-slate-500">Add and manage your bank accounts, e-wallets, and cash balances.</p></div>
                        <Link href={route('personal.accounts')} className="ss-button h-11 gap-2 px-4"><Landmark size={18}/>Manage accounts</Link>
                    </div>

                    <div className="ss-card flex flex-wrap items-center justify-between gap-4 p-6 sm:p-7">
                        <div><h2 className="font-display text-xl font-bold">Signed in on this device</h2><p className="mt-1 text-sm text-slate-500">End this session securely when you are finished.</p></div>
                        <Link href={route('logout')} method="post" as="button" className="inline-flex h-11 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700"><LogOut size={18}/>Log out</Link>
                    </div>

                    <div className="rounded-[1.65rem] border border-rose-100 bg-rose-50/50 p-6 sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
