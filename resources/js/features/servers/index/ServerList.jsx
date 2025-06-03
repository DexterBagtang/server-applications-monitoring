import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table.js"
import { Badge } from "@/components/ui/badge.js"
import {CheckCircle, ChevronDown, Clock} from "lucide-react";
import ServerStatusBadge from "@/features/servers/ui/ServerStatusBadge.jsx";

export function ServerList({ servers, onServerSelect }) {


    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Hostname</TableHead>
                        <TableHead>IP Address</TableHead>
                        <TableHead>OS</TableHead>
                        <TableHead>Resources</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Last Ping</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {servers.length > 0 ? (
                        servers.map((server) => (
                            <TableRow
                                key={server.id}
                                className="cursor-pointer hover:bg-muted/50"
                                onClick={() => onServerSelect?.(server)}
                            >
                                <TableCell className="font-medium">{server.name}</TableCell>
                                <TableCell>{server.hostname}</TableCell>
                                <TableCell>{server.ip_address}</TableCell>
                                <TableCell>
                                    <div className="flex items-center">
                                        {server.os_type && (
                                            <span className="capitalize">{server.os_type}</span>
                                        )}
                                        {server.os_version && (
                                            <span className="text-muted-foreground ml-1">
                            {server.os_version}
                          </span>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex space-x-2">
                                        <Badge variant="secondary">
                                            {server.cpu_cores || "?"} cores
                                        </Badge>
                                        <Badge variant="secondary">
                                            {server.ram_gb || "?"} GB
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <ServerStatusBadge server={server} />
                                </TableCell>
                                <TableCell>
                                    {server.last_ping_at ? (
                                        <div className="flex items-center">
                                            <Clock className="h-3 w-3 mr-1 text-muted-foreground" />
                                            {new Date(server.last_ping_at).toLocaleString()}
                                        </div>
                                    ) : (
                                        "Never"
                                    )}
                                </TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell colSpan={7} className="h-24 text-center">
                                No servers found
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    )
}
