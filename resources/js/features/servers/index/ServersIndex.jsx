import {CheckCircle, ChevronDown, Clock, LayoutGridIcon, TableIcon} from "lucide-react";
import {useEffect, useState} from "react";
import {Button} from "@/components/ui/button.js";
import {ServerList} from "@/features/servers/index/ServerList.jsx";
import {ServerCards} from "@/features/servers/index/ServerCards.jsx";
import {Input} from "@/components/ui/input.js";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from "@/components/ui/dropdown-menu.js";
import {Table, TableBody, TableCell, TableHead, TableHeader, TableRow} from "@/components/ui/table.js";
import {Badge} from "@/components/ui/badge.js";
import {Card, CardContent, CardHeader} from "@/components/ui/card.js";
import AddServerDialog from "@/features/servers/index/AddServerDialog.jsx";
import {router} from "@inertiajs/react";

export default function ServersIndex({servers}) {
    const [viewMode, setViewMode] = useState("cards"); // default to cards
    const [searchTerm, setSearchTerm] = useState("")
    const [osFilter, setOsFilter] = useState(null)
    const [statusFilter, setStatusFilter] = useState(null)

    const filteredServers = servers.filter((server) => {
        const matchesSearch =
            server.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            server.hostname?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            server.ip_address.toLowerCase().includes(searchTerm.toLowerCase())

        const matchesOs = osFilter ? server.os_type === osFilter : true
        const matchesStatus =
            statusFilter !== null ? server.status === statusFilter : true

        return matchesSearch && matchesOs && matchesStatus
    })

    const osTypes = ["ubuntu", "centos", "almalinux", "debian", "rocky", "other"]


    function toggleViewMode() {
        setViewMode(prev => prev === "cards" ? "table" : "cards");
    }

    function handleServerSelect(server){
        router.get(`/servers/${server.id}`)
    }


    return (
        <div>
            <div className="flex justify-end">

            </div>

            <Card>
                <CardHeader className="flex flex-col space-y-4">
                    <div className="flex flex-col space-y-4 md:flex-row md:space-y-0 md:space-x-4 md:items-center">
                        <Input
                            placeholder="Search servers by name, hostname or IP..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="max-w-md"
                        />

                        <div className="flex space-x-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        {osFilter ? (
                                            <span className="capitalize">{osFilter}</span>
                                        ) : (
                                            "All OS Types"
                                        )}
                                        <ChevronDown className="ml-2 h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem onClick={() => setOsFilter(null)}>
                                        All OS Types
                                    </DropdownMenuItem>
                                    {osTypes.map((os) => (
                                        <DropdownMenuItem
                                            key={os}
                                            onClick={() => setOsFilter(os)}
                                            className="capitalize"
                                        >
                                            {os}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        {statusFilter === "Online"
                                            ? "Online"
                                            : statusFilter === "Offline"
                                                ? "Offline"
                                                : "All Statuses"}
                                        <ChevronDown className="ml-2 h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem onClick={() => setStatusFilter(null)}>
                                        All Statuses
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => setStatusFilter('Online')}>
                                        Online
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => setStatusFilter('Offline')}>
                                        Offline
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={toggleViewMode}
                        >
                            {viewMode === "table" ? (
                                <TableIcon className="h-4 w-4"/>
                            ) : (
                                <LayoutGridIcon className="h-4 w-4"/>
                            )}
                        </Button>

                        <AddServerDialog />
                    </div>
                </CardHeader>
                <CardContent>
                    {viewMode === 'table' ?
                        <ServerList servers={filteredServers} onServerSelect={handleServerSelect}/>
                        : <ServerCards servers={filteredServers} onServerSelect={handleServerSelect}/>
                    }
                </CardContent>
            </Card>

        </div>
    );
}
