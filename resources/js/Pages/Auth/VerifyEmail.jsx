import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Επαλήθευση Email" />

            <div className="mb-4 text-sm text-gray-600">
                Ευχαριστούμε για την εγγραφή! Πριν ξεκινήσετε, θα μπορούσατε να επαληθεύσετε
                τη διεύθυνση email σας κάνοντας κλικ στον σύνδεσμο που μόλις σας στείλαμε;
                Αν δεν λάβατε το email, θα σας στείλουμε ευχαρίστως
                ένα άλλο.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    Ένας νέος σύνδεσμος επαλήθευσης έχει σταλεί στη διεύθυνση email
                    που δώσατε κατά την εγγραφή.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>
                        Επανάληψη Email Επαλήθευσης
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Αποσύνδεση
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
