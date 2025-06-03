import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import {Head, router} from '@inertiajs/react';
import ServersIndex from "@/features/servers/index/ServersIndex";
import {useEffect} from "react";
import {toast} from "sonner";


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Servers',
        href: '/servers',
    },
];

export default function Servers({servers}) {
    useEffect(() => {
        const channel = window.Echo.channel('server-details')
            .listen('ServerDetailsFetched', (e) => {
                console.log('server-details:', e);
                router.reload({
                    onSuccess:()=>{
                        toast('server updated')
                    }
                })
            });

        return () => {
            channel.stopListening('ServerDetailsFetched');
        };
    }, []);


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Servers" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <ServersIndex servers={servers} />
            </div>
        </AppLayout>
    );
}
