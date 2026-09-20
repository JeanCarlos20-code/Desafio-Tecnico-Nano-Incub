import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { store } from '../../Services/rooms';
import RoomForm from './Components/RoomForm';

const GENERAL_FAILURE = 'Não foi possível salvar a sala. Tente novamente.';

export default function Create() {
    const form = useForm({
        name: '',
        capacity: '',
    });
    const [generalError, setGeneralError] = useState('');

    function showGeneralFailure() {
        setGeneralError(GENERAL_FAILURE);

        return false;
    }

    function submit() {
        if (form.processing) {
            return;
        }

        setGeneralError('');

        store(form, {
            onError: (errors) => {
                const first = ['name', 'capacity'].find((field) => errors[field]);

                if (first) {
                    document.getElementById(first)?.focus();
                }
            },
            onInvalid: showGeneralFailure,
            onException: showGeneralFailure,
        });
    }

    return (
        <AppLayout>
            <RoomForm mode="create" form={form} generalError={generalError} onSubmit={submit} />
        </AppLayout>
    );
}
