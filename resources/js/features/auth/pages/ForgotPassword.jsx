import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        // Get CSRF token from meta tag
        const token = document.head.querySelector('meta[name="csrf-token"]');
        if (!token) {
            console.error('CSRF token not found for password reset');
            return;
        }

        post(route('password.email'), {
            headers: {
                'X-CSRF-TOKEN': token.content,
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Ξεχάσατε τον Κωδικό" />

            <div className="mb-4 text-sm text-gray-600">
                Ξεχάσατε τον κωδικό σας; Κανένα πρόβλημα. Απλά ενημερώστε μας τη διεύθυνση email
                σας και θα σας στείλουμε έναν σύνδεσμο επαναφοράς κωδικού που θα
                σας επιτρέψει να επιλέξετε έναν νέο.
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <InputError message={errors.email} className="mt-2" />

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton className="ms-4" disabled={processing}>
                        Αποστολή Συνδέσμου Επαναφοράς
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
