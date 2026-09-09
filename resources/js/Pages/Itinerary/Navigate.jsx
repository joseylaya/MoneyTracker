import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DayMap from '@/Components/Itinerary/DayMap';
import { Head } from '@inertiajs/react';

export default function Navigate({ tracker, day, canManage }) {
    return <AuthenticatedLayout fullBleed>
        <Head title={`Navigate · ${tracker.name}`}/>
        <DayMap
            trackerId={tracker.id}
            day={day}
            canManage={canManage}
            navigationOnly
            backHref={route('trackers.itinerary.index', tracker.id)}
        />
    </AuthenticatedLayout>;
}
