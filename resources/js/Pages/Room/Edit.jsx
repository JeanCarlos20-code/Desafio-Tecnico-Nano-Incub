import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { update } from '../../Services/rooms';
import { RoomForm } from './Create';

export default function Edit({ room }) {
    const form = useForm({
        name: room.name,
        capacity: room.capacity,
        is_active: room.is_active,
    });

    function submit(event) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        update(form, room.id);
    }

    return (
        <AppLayout>
            <h1 className="text-2xl font-semibold text-slate-900">Editar sala</h1>
            <p className="mt-1 text-sm text-slate-600">Atualize os dados da sala de reunião.</p>
            <RoomForm form={form} onSubmit={submit} submitLabel="Salvar alterações" processingLabel="Salvando..." />
        </AppLayout>
    );
}
