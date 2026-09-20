import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { update } from '../../Services/rooms';
import RoomForm from './Components/RoomForm';

const GENERAL_FAILURE = 'Não foi possível salvar a sala. Tente novamente.';

export default function Edit({ room, future_active_count = 0 }) {
    const form = useForm({
        name: room.name,
        capacity: room.capacity,
        is_active: room.is_active,
        scheduled_meetings_action: '',
    });
    const [generalError, setGeneralError] = useState('');
    const [reopenDialog, setReopenDialog] = useState(false);
    const [futureActiveCount, setFutureActiveCount] = useState(future_active_count);

    function showGeneralFailure() {
        setGeneralError(GENERAL_FAILURE);

        return false;
    }

    function visitOptions() {
        return {
            onError: (errors) => {
                if (errors.scheduled_meetings_action || errors.future_active_count) {
                    const count = Number.parseInt(errors.future_active_count, 10);

                    if (Number.isFinite(count) && count > 0) {
                        setFutureActiveCount(count);
                    }

                    setReopenDialog(true);
                }

                const first = ['name', 'capacity', 'is_active'].find((field) => errors[field]);

                if (first) {
                    document.getElementById(first)?.focus();
                }
            },
            onInvalid: showGeneralFailure,
            onException: showGeneralFailure,
        };
    }

    function submit(action = '') {
        if (form.processing) {
            return;
        }

        setGeneralError('');
        setReopenDialog(false);
        form.transform((data) => ({
            name: data.name,
            capacity: data.capacity,
            is_active: data.is_active,
            ...(action ? { scheduled_meetings_action: action } : {}),
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
                futureActiveCount={futureActiveCount}
                reopenDialog={reopenDialog}
                onSubmit={submit}
                onConfirmDeactivate={(action) => submit(action)}
                onDismissReopen={() => setReopenDialog(false)}
            />
        </AppLayout>
    );
}
