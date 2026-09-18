import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { update } from '../../Services/rooms';
import RoomForm from './Components/RoomForm';

const GENERAL_FAILURE = 'Não foi possível salvar a sala. Tente novamente.';

export default function Edit({ room, has_registered_meetings = false }) {
    const form = useForm({
        name: room.name,
        capacity: room.capacity,
    });
    const [generalError, setGeneralError] = useState('');

    function showGeneralFailure() {
        setGeneralError(GENERAL_FAILURE);

        return false;
    }

    function visitOptions() {
        return {
            onError: (errors) => {
                const first = ['name', 'capacity'].find((field) => errors[field]);

                if (first) {
                    document.getElementById(first)?.focus();
                }
            },
            onHttpException: showGeneralFailure,
            onNetworkError: showGeneralFailure,
        };
    }

    function submit() {
        if (form.processing) {
            return;
        }

        setGeneralError('');
        form.transform((data) => ({
            name: data.name,
            capacity: data.capacity,
        }));
        update(form, room.id, visitOptions());
    }

    function confirmDeactivate() {
        if (form.processing) {
            return;
        }

        setGeneralError('');
        form.transform((data) => ({
            name: data.name,
            capacity: data.capacity,
            is_active: false,
        }));
        update(form, room.id, visitOptions());
    }

    function confirmActivate() {
        if (form.processing) {
            return;
        }

        setGeneralError('');
        form.transform((data) => ({
            name: data.name,
            capacity: data.capacity,
            is_active: true,
        }));
        update(form, room.id, visitOptions());
    }

    return (
        <AppLayout>
            <RoomForm
                mode="edit"
                form={form}
                generalError={generalError}
                isActive={room.is_active}
                hasRegisteredMeetings={has_registered_meetings}
                onSubmit={submit}
                onConfirmDeactivate={confirmDeactivate}
                onConfirmActivate={confirmActivate}
            />
        </AppLayout>
    );
}
