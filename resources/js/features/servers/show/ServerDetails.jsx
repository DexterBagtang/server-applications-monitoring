import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from "@/components/ui/card";
import {Badge} from "@/components/ui/badge";
import {Separator} from "@/components/ui/separator";
import {Alert, AlertDescription} from "@/components/ui/alert";
import {
    Terminal,
    ChevronDown,
    ChevronUp,
    ExternalLinkIcon,
    RefreshCw,
    Settings,
    ExternalLink,
    RefreshCwIcon, RefreshCcwDot, RefreshCcwIcon, MoreVertical, Edit, PowerOff, Trash2, Loader2Icon
} from "lucide-react";
import {formatDistanceToNow} from "date-fns";
import {useState} from "react";
import {Button} from "@/components/ui/button.js";
import {ServicesCard} from "@/features/servers/show/ServicesCard.jsx";
import AddApplicationDialog from "@/features/applications/AddApplicationDialog.jsx";
import ApplicationsCard from "@/features/applications/show/ApplicationsCard.jsx";
import {router} from "@inertiajs/react";
import {toast} from "sonner";
import {ServerTerminal} from "@/features/servers/show/ServerTerminal.jsx";
import {
    DropdownMenu,
    DropdownMenuContent, DropdownMenuItem,
    DropdownMenuLabel, DropdownMenuSeparator,
    DropdownMenuTrigger
} from "@/components/ui/dropdown-menu.js";
import EditServerDialog from "@/features/servers/index/EditServerDialog.jsx";
import {DeleteServerDialog} from "@/features/servers/index/DeleteServerDialog.jsx";
import FileDownloadManager from "@/features/applications/show/FileDownloadManager.jsx";

export function ServerDetails({server,servers}) {
    // console.log(servers)
    const [showMetrics, setShowMetrics] = useState(false);
    const lastPingTime = formatDistanceToNow(new Date(server.last_ping_at), {
        addSuffix: true,
    });
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [menuOpen , setMenuOpen] = useState(false);


    const handleEditServer = (e) => {
        e.stopPropagation();
        setMenuOpen(false);
        setEditDialogOpen(true);
    };

    function handleRefetchDetails(id) {
        router.get(`/servers/${id}/fetch`, {}, {})
    }


    function handleDeleteServer() {
        setMenuOpen(false)
        setDeleteDialogOpen(true)
    }

    return (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {/* Left Column - Server Info */}
            <div className="space-y-4 lg:col-span-1">
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-xl flex justify-between items-center">
                            <div className="flex items-center gap-2">
                                {server.name}
                                <Badge
                                    variant={server.status === "Online" ? "default" : "destructive"}
                                >
                                    {server.status}{server.status === "Fetching" ? <Loader2Icon className="animate-spin" /> : ''}
                                </Badge>
                            </div>
                            <div className="flex gap-1">
                                <Button onClick={() => handleRefetchDetails(server.id)} variant="outline" size="sm"
                                        className="text-xs">
                                    <RefreshCcwIcon className='h-2 w-2' />
                                </Button>
                                <DropdownMenu open={menuOpen} onOpenChange={setMenuOpen}>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" size="icon" className="h-7 w-7 -mr-1">
                                            <MoreVertical className="h-4 w-4"/>
                                            <span className="sr-only">Open menu</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-48">
                                        <DropdownMenuLabel>Server Actions</DropdownMenuLabel>
                                        <DropdownMenuSeparator/>
                                        <DropdownMenuItem onClick={handleEditServer}>
                                            <Edit className="mr-2 h-4 w-4" />
                                            <span>Edit Server</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator/>
                                        <DropdownMenuItem
                                            disabled={server.status !== 'Online'}
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <PowerOff className="mr-2 h-4 w-4"/>
                                            <span>Restart Server</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            className="text-destructive focus:text-destructive"
                                            onClick={handleDeleteServer}
                                        >
                                            <Trash2 className="mr-2 h-4 w-4" />
                                            <span>Delete Server</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>


                        </CardTitle>

                        <CardDescription>
                            {server.hostname} • {server.os_version}
                        </CardDescription>
                    </CardHeader>
                    <Separator/>
                    <CardContent className="pt-4 grid gap-3">
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <p className="text-sm text-muted-foreground">CPU</p>
                                <p className="text-sm font-medium">
                                    {server.cpu_cores} cores
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Memory</p>
                                <p className="text-sm font-medium">{server.ram_gb} GB</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Storage</p>
                                <p className="text-sm font-medium">{server.disk_gb} GB</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Last Ping</p>
                                <p className="text-sm font-medium">{lastPingTime}</p>
                            </div>
                        </div>

                        <Separator className="my-2"/>

                        <div>
                            <p className="text-sm text-muted-foreground">IP Address</p>
                            <p className="text-sm font-medium">
                                {server.ip_address} (Public: {server.public_ip})
                            </p>
                        </div>

                        <div>
                            <p className="text-sm text-muted-foreground">Gateway</p>
                            <p className="text-sm font-medium">{server.gateway}</p>
                        </div>
                    </CardContent>
                    {server.remarks && (
                        <CardFooter>
                            <Alert variant="destructive" className="w-full">
                                <Terminal className="h-4 w-4"/>
                                <AlertDescription>{server.remarks}</AlertDescription>
                            </Alert>
                        </CardFooter>
                    )}
                </Card>

                {server.status === 'Online' &&
                    <div className="flex gap-2">
                        <ServerTerminal server={server}/>
                        <FileDownloadManager server={server} servers={servers} />
                    </div>
                }

            </div>

            {/* Right Column - Primary Content */}
            <div className="space-y-4 lg:col-span-2">
                {/* Compact Application Card */}


                <ApplicationsCard
                    applications={server.applications}
                    server={server}
                />

                <ServicesCard services={server.services} serverId={server.id}/>


                {/* Collapsible Metrics - Even More Compact */}
                <div className="border rounded-lg">
                    <Button
                        variant="ghost"
                        className="w-full p-3 flex justify-between items-center text-sm"
                        onClick={() => setShowMetrics(!showMetrics)}
                    >
                        <span className="font-medium">Performance Metrics</span>
                        {showMetrics ? (
                            <ChevronUp className="h-4 w-4 text-muted-foreground"/>
                        ) : (
                            <ChevronDown className="h-4 w-4 text-muted-foreground"/>
                        )}
                    </Button>
                    {showMetrics && (
                        <div className="p-3 border-t grid grid-cols-2 gap-3">
                            <div
                                className="h-20 flex items-center justify-center border rounded text-xs text-muted-foreground">
                                CPU Usage
                            </div>
                            <div
                                className="h-20 flex items-center justify-center border rounded text-xs text-muted-foreground">
                                Memory
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <EditServerDialog
                isOpen={editDialogOpen}
                onClose={() => setEditDialogOpen(false)}
                server={server}
            />

            <DeleteServerDialog
                server={server}
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            />
        </div>
    );
}
