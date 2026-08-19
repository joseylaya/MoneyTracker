import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        sessionStorage.setItem('splitshare:pwa-install-guide-after-auth', '1');

        post(route('login'), {
            onError: () => sessionStorage.removeItem('splitshare:pwa-install-guide-after-auth'),
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <h1 className="font-display text-3xl font-bold tracking-tight text-slate-900">Welcome back</h1><p className="mt-2 text-sm leading-6 text-slate-500">Sign in to see your trackers, balances, and shared updates.</p>
            {status && <div className="mt-5 rounded-2xl bg-[#e7faee] px-4 py-3 text-sm font-medium text-[#138e48]">{status}</div>}

            <form onSubmit={submit} className="mt-7 space-y-5">
                <div>
                    <label htmlFor="email" className="text-sm font-bold text-slate-700">Email address</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-2 ss-input"
                        autoComplete="username"
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <div className="flex items-center justify-between"><label htmlFor="password" className="text-sm font-bold text-slate-700">Password</label>{canResetPassword && <Link href={route('password.request')} className="text-xs font-bold text-[#18ad58]">Forgot password?</Link>}</div>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-2 ss-input"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="block">
                    <label className="flex items-center gap-2 text-sm text-slate-500">
                        <input
                            name="remember"
                            checked={data.remember}
                            type="checkbox"
                            className="size-4 rounded border-slate-300 text-[#2ecc70] focus:ring-[#2ecc70]"
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span>Keep me signed in</span>
                    </label>
                </div>

                <button className="ss-button w-full" disabled={processing}>{processing ? 'Signing in…' : 'Log in'}</button>
                <p className="text-center text-sm text-slate-500">New to SplitShare? <Link href={route('register')} className="font-bold text-[#18ad58]">Create an account</Link></p>
            </form>
        </GuestLayout>
    );
}
