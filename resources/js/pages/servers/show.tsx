import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import {Head, router} from '@inertiajs/react';
import ServersIndex from "@/features/servers/index/ServersIndex";
import {useEffect} from "react";
import {toast} from "sonner";
import {ServerDetails} from "@/features/servers/show/ServerDetails";


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Server Details',
        href: '/servers/{server}',
    },
];

export default function ServerPage({server,servers}) {

    useEffect(() => {
        const channel = window.Echo.channel('server-details')
            .listen('ServerDetailsFetched', (e) => {
                router.reload({
                    // onSuccess:()=>{
                    //     toast('yessss server updated')
                    // }
                })
            });

        return () => {
            channel.stopListening('ServerDetailsFetched');
        };
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Server Details" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/*<ServersIndex servers={servers} />*/}
                {/*<div>{server.name}</div>*/}
                <ServerDetails server={server} servers={servers}/>
            </div>
        </AppLayout>
    );
}
