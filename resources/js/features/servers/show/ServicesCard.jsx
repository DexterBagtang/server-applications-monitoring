import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
} from "@/components/ui/card";
import {Badge} from "@/components/ui/badge";
import {Button} from "@/components/ui/button";
import {ChevronDown, ChevronUp, Loader2, RefreshCcwIcon} from "lucide-react";
import {useState} from "react";
import axios from "axios";
import {Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle} from "@/components/ui/dialog.js";
import {Input} from "@/components/ui/input.js";
import {router} from "@inertiajs/react";
import {toast} from "sonner";

export function ServicesCard({services,serverId}) {
    const [expanded, setExpanded] = useState(false);
    const [filter, setFilter] = useState('running');
    const [search,setSearch] = useState("");
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [serviceDetails, setServiceDetails] = useState("");

    // Group services by status
    const filteredServices = services.filter(service => {
        const matchesFilter =
            filter === 'all' ||
            (filter === 'running' && service.status.includes('running')) ||
            (filter === 'failed' && (service.status.includes('failed') || service.status.includes('inactive')));

        const matchesSearch = search === '' || service.name.includes(search);

        return matchesFilter && matchesSearch;
    });

    const visibleServices = expanded ? filteredServices : filteredServices.slice(0, 3);

    async function handleShowService(service) {
        setLoading(true);
        setOpen(true);
        try {
            const response = await axios.get(`/api/servers/${service.server_id}/${service.name}/service-details`)
            setServiceDetails(response.data);
        } finally {
            setLoading(false);
        }
    }

    function handleDialogOpenChange(open) {
        setOpen(open);
        if (!open) {
            setServiceDetails("");
        }
    }

    const handleFetchServices = async () => {
        setLoading(true)
        try {
            const response = await axios.get(`/services/${serverId}/fetch`)
            if (response.data === 'successful') {
                router.reload({
                    onSuccess: () => {
                        toast.success('Fetch services successfully!')
                    }
                })
            } else {
                toast.error('There is an error fetching services!')
            }
        } catch (error) {
            console.error(error)
            toast.error('An unexpected error occurred.')
        } finally {
            setLoading(false)
        }
    }

    return (
        <>
            <Card>
                <CardHeader className="pb-3">
                    <div className="flex justify-between items-center">
                        <CardTitle className="text-lg flex items-center gap-2">
                            Server Services
                            <Badge variant="outline" className="px-2 py-0.5 text-xs">
                                {services.length} total
                            </Badge>
                            <Button variant="outline" size="sm" onClick={handleFetchServices} disabled={loading}>
                                <RefreshCcwIcon className={loading ? 'animate-spin' : ''} />
                            </Button>
                        </CardTitle>
                        <div className="flex gap-2 items-center">
                            <Button
                                variant={filter === 'all' ? 'default' : 'ghost'}
                                size="sm"
                                className="h-7 px-2 text-xs"
                                onClick={() => setFilter('all')}
                            >
                                All
                            </Button>
                            <Button
                                variant={filter === 'running' ? 'default' : 'ghost'}
                                size="sm"
                                className="h-7 px-2 text-xs"
                                onClick={() => setFilter('running')}
                            >
                                Running
                            </Button>
                            <Button
                                variant={filter === 'failed' ? 'default' : 'ghost'}
                                size="sm"
                                className="h-7 px-2 text-xs"
                                onClick={() => setFilter('failed')}
                            >
                                Issues
                            </Button>

                            <Input type="search"
                                   value={search}
                                   onChange={e=>setSearch(e.target.value)}
                                   placeholder="Search services..."
                            />

                        </div>
                    </div>
                </CardHeader>
                <CardContent className="pt-0">
                    <div className="space-y-2">
                        {visibleServices.length === 0 ? (
                            <div className="text-sm text-center text-muted-foreground py-4">
                                No services found.
                            </div>
                        ) : (
                            visibleServices.map((service) => (
                                <div
                                    key={service.id}
                                    className="flex items-center cursor-pointer justify-between p-2 text-sm hover:bg-muted/50 rounded"
                                    onClick={() => handleShowService(service)}
                                >
                                    <div className="flex items-center gap-2 truncate">
                                        <div
                                            className={`h-2 w-2 rounded-full ${
                                                service.status.includes('running')
                                                    ? 'bg-green-500'
                                                    : service.status.includes('failed')
                                                        ? 'bg-red-500'
                                                        : 'bg-yellow-500'
                                            }`}
                                        />
                                        <span className="font-medium truncate">{service.name}</span>
                                        <span className="text-muted-foreground truncate hidden sm:inline">
                                            {service.description}
                                          </span>
                                    </div>
                                    <Badge variant="outline" className="px-1.5 text-xs capitalize">
                                        {service.status}
                                    </Badge>
                                </div>
                            ))
                        )}
                    </div>


                    {filteredServices.length > 3 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="w-full mt-2 h-7 text-xs"
                            onClick={() => setExpanded(!expanded)}
                        >
                            {expanded ? (
                                <>
                                    <ChevronUp className="h-3 w-3 mr-1"/>
                                    Show less
                                </>
                            ) : (
                                <>
                                    <ChevronDown className="h-3 w-3 mr-1"/>
                                    Show {filteredServices.length - 3} more
                                </>
                            )}
                        </Button>
                    )}
                </CardContent>
            </Card>

            <Dialog open={open} onOpenChange={handleDialogOpenChange}>
                <DialogContent className="!max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Service Status</DialogTitle>
                    </DialogHeader>
                    <div className="max-h-[70vh] overflow-auto">
                        {loading ? (
                            <div className="flex items-center justify-center h-32">
                                <Loader2 className="h-8 w-8 animate-spin" />
                            </div>
                        ) : (
                            <pre className="whitespace-pre-wrap font-mono text-sm p-4 bg-background border rounded-lg">
                                {serviceDetails || "No service details available"}
                            </pre>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
