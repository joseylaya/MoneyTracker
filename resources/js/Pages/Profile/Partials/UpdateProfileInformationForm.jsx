import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;
    const photoInput = useRef(null);
    const [photo, setPhoto] = useState(null);
    const [photoPreview, setPhotoPreview] = useState(null);
    const [photoError, setPhotoError] = useState('');
    const [photoProcessing, setPhotoProcessing] = useState(false);

    useEffect(() => () => { if (photoPreview) URL.revokeObjectURL(photoPreview); }, [photoPreview]);

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            username: user.username,
            email: user.email || '',
        });

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    const choosePhoto = (event) => {
        const selected = event.target.files?.[0] || null;
        if (photoPreview) URL.revokeObjectURL(photoPreview);
        setPhoto(selected);
        setPhotoPreview(selected ? URL.createObjectURL(selected) : null);
        setPhotoError('');
    };

    const uploadPhoto = () => {
        if (!photo) return;
        setPhotoProcessing(true);
        router.post(route('profile.photo.update'), { photo }, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => setPhotoError(errors.photo || 'The profile photo could not be uploaded.'),
            onSuccess: () => { setPhoto(null); setPhotoPreview(null); if (photoInput.current) photoInput.current.value = ''; },
            onFinish: () => setPhotoProcessing(false),
        });
    };

    const removePhoto = () => router.delete(route('profile.photo.destroy'), { preserveScroll: true });

    return (
        <section className={className}>
            <header>
                <h2 className="font-display text-xl font-bold text-slate-900">Profile information</h2>

                <p className="mt-1 text-sm leading-6 text-slate-500">
                    Update your account's profile information and email address.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel value="Profile photo" />
                    <div className="mt-3 flex flex-wrap items-center gap-4">
                        <div className="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-white bg-[#e0f8ea] text-2xl font-bold text-[#13a856] shadow-md">
                            {photoPreview || user.avatar_url ? <img src={photoPreview || user.avatar_url} alt="Profile preview" className="size-full object-cover"/> : user.name.slice(0, 1).toUpperCase()}
                        </div>
                        <div className="min-w-0 flex-1">
                            <input ref={photoInput} type="file" accept="image/jpeg,image/png" onChange={choosePhoto} className="hidden" />
                            <div className="flex flex-wrap gap-2">
                                <button type="button" onClick={() => photoInput.current?.click()} className="ss-button-secondary h-11 gap-2 px-4"><Camera size={18}/>{user.avatar_url ? 'Change photo' : 'Choose photo'}</button>
                                {photo && <button type="button" onClick={uploadPhoto} disabled={photoProcessing} className="ss-button h-11 px-4">{photoProcessing ? 'Converting…' : 'Save photo'}</button>}
                                {user.avatar_url && !photo && <button type="button" onClick={removePhoto} className="inline-flex h-11 items-center gap-2 rounded-2xl bg-rose-50 px-4 text-sm font-bold text-rose-600"><Trash2 size={17}/>Remove</button>}
                            </div>
                            <p className="mt-2 text-xs leading-5 text-slate-500">JPEG or PNG, up to 5 MB. SplitShare resizes and converts it to a compact WebP file.</p>
                            {photoError && <p className="mt-2 text-sm font-semibold text-rose-600">{photoError}</p>}
                        </div>
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="username" value="Login nickname" />
                    <TextInput id="username" className="mt-1 block w-full" value={data.username} onChange={(e) => setData('username', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))} required autoComplete="username" />
                    <InputError className="mt-2" message={errors.username} />
                </div>

                <div>
                    <InputLabel htmlFor="name" value="Name" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-slate-700">
                            Your email address is unverified.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                A new verification link has been sent to your
                                email address.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
