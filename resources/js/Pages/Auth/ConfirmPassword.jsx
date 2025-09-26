import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();

        // Get CSRF token from meta tag
        const token = document.head.querySelector('meta[name="csrf-token"]');
        if (!token) {
            console.error('CSRF token not found for password confirmation');
            return;
        }

        post(route('password.confirm'), {
            headers: {
                'X-CSRF-TOKEN': token.content,
            },
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Επιβεβαίωση Κωδικού" />

            <div className="mb-4 text-sm text-gray-600">
                Αυτή είναι μια ασφαλής περιοχή της εφαρμογής. Παρακαλώ επιβεβαιώστε τον
                κωδικό σας πριν συνεχίσετε.
            </div>

            <form onSubmit={submit}>
                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Κωδικός Πρόσβασης" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton className="ms-4" disabled={processing}>
                        Επιβεβαίωση
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
