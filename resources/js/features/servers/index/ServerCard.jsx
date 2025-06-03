import {
    CheckCircle,
    CircleHelpIcon,
    Clock,
    LoaderIcon,
    Server,
    MoreVertical,
    Settings,
    Edit,
    Trash2,
    PowerOff,
    MonitorIcon,
    Cpu, MemoryStick,
} from "lucide-react";
import {Badge} from "@/components/ui/badge";
import {Card, CardContent} from "@/components/ui/card";
import {Popover, PopoverContent, PopoverTrigger} from "@/components/ui/popover";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger
} from "@/components/ui/dropdown-menu";
import {Button} from "@/components/ui/button";
import {Tooltip, TooltipContent, TooltipProvider, TooltipTrigger} from "@/components/ui/tooltip";
import ServerStatusBadge from "@/features/servers/ui/ServerStatusBadge.jsx";
import {useState} from "react";
import EditServerDialog from "@/features/servers/index/EditServerDialog.jsx";
import {DeleteServerDialog} from "@/features/servers/index/DeleteServerDialog.jsx";
import {router} from "@inertiajs/react";

export default function ServerCard({server, onServerSelect, onEditServer}) {

    const [menuOpen, setMenuOpen] = useState(false)
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    // Format OS info to ensure it's properly displayed
    const osInfo = server.os_type || "Unknown";
    const osVersion = server.os_version ? `${server.os_version}` : osInfo;

    function handleEditServer (e) {
        e.stopPropagation()
        setMenuOpen(false)
        setEditDialogOpen(true);
    }

    return (
        <>
            <Card
                className="transition-all hover:shadow-md dark:hover:shadow-primary/10 hover:border-primary/50 cursor-pointer"
                onClick={() => onServerSelect?.(server)}
            >
                <CardContent className="">
                    <div className="flex items-start">
                        {/* Server Icon */}
                        <div className="bg-secondary p-2 rounded-lg mr-3">
                            <Server className="h-4 w-4 text-primary"/>
                        </div>

                        {/* Main Content */}
                        <div className="flex-1 min-w-0">
                            {/* Header: Name and Actions */}
                            <div className="flex justify-between items-center mb-2">
                                <div>
                                    <h3 className="font-medium text-base leading-tight">{server.name}</h3>
                                    <p className="text-xs text-muted-foreground truncate">{server.hostname || "Unknown"}</p>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <ServerStatusBadge server={server}/>


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
                                            {/*<DropdownMenuItem onClick={(e) => {*/}
                                            {/*    e.stopPropagation();*/}
                                            {/*    onServerSelect?.(server);*/}
                                            {/*    router.get(`/servers/${server.id}`)*/}
                                            {/*}}>*/}
                                            {/*    <Settings className="mr-2 h-4 w-4"/>*/}
                                            {/*    <span>View Details</span>*/}
                                            {/*</DropdownMenuItem>*/}

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
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setMenuOpen(false)
                                                    setDeleteDialogOpen(true);
                                                }}
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                <span>Delete Server</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>

                                </div>
                            </div>

                            {/* Details Grid */}
                            <div className="grid grid-cols-1 gap-2 text-xs">
                                {/* IP Address */}
                                <div className="flex items-center">
                                    <span className="font-medium text-muted-foreground w-8">IP:</span>
                                    <span className="font-mono">{server.ip_address}</span>
                                </div>

                                {/* OS Info - Full Version */}
                                <div className="flex items-center">
                                    <span className="font-medium text-muted-foreground w-8">OS:</span>
                                    <div className="flex items-center overflow-hidden">
                                        <MonitorIcon className="h-3 w-3 mr-1 flex-shrink-0"/>
                                        <span className="mr-1">{osVersion}</span>
                                        {/*{server.os_version && (*/}
                                        {/*    <span className="text-xs text-muted-foreground">{osVersion}</span>*/}
                                        {/*)}*/}
                                    </div>
                                </div>

                                {/* Hardware Specs */}
                                <div className="flex items-center">
                                    <span className="font-medium text-muted-foreground w-8"></span>
                                    <div className="flex items-center space-x-2">
                                        <div className="flex items-center">
                                            <Cpu className="h-3 w-3 mr-1"/>
                                            <span>{server.cpu_cores || "?"} cores</span>
                                        </div>
                                        <div className="flex items-center">
                                            <MemoryStick className="h-3 w-3 mr-1"/>
                                            <span>{server.ram_gb || "?"} GB</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Last Ping */}
                                <div className="flex items-center text-xs text-muted-foreground mt-1">
                                    <span className="font-medium w-8"></span>
                                    <Clock className="h-3 w-3 mr-1"/>
                                    <span className="mr-1">Last ping:</span>
                                    <span className="font-medium">
                                    {server.last_ping_at
                                        ? new Date(server.last_ping_at).toLocaleString()
                                        : "Never pinged"}
                                </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Edit Server Dialog */}
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

        </>
    );
}


