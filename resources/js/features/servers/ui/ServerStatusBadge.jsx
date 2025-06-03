import {Badge} from "@/components/ui/badge.js";
import {CheckCircle, CircleHelpIcon, LoaderIcon} from "lucide-react";
import {Popover, PopoverContent, PopoverTrigger} from "@/components/ui/popover.js";

export default function ServerStatusBadge({ server }) {
    if (server.status === 'Online') {
        return (
            <Badge variant="success" className="flex items-center h-6">
                <CheckCircle className="h-3 w-3 mr-1" />
                Online
            </Badge>
        );
    }

    if (server.status === 'Fetching') {
        return (
            <Badge variant="secondary" className="flex items-center h-6">
                <LoaderIcon className="h-3 w-3 mr-1 animate-spin" />
                Fetching
            </Badge>
        );
    }

    if (server.status === 'Offline') {
        return (
            <Popover>
                <PopoverTrigger asChild onClick={(e) => e.stopPropagation()}>
                    <Badge variant="destructive" className="flex items-center cursor-pointer h-6">
                        <span>Offline</span>
                        <CircleHelpIcon className="w-3 h-3 ml-1" />
                    </Badge>
                </PopoverTrigger>
                <PopoverContent className="w-64" onClick={(e) => e.stopPropagation()}>
                    <div className="flex flex-col space-y-2">
                        <div className="flex items-center gap-2">
                            <span className="flex h-2 w-2 relative">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                            </span>
                            <h3 className="font-semibold">Server Offline</h3>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            The server is currently unreachable or down. This could be due to maintenance, network issues, or unexpected downtime.
                        </p>
                        {server.remarks && (
                            <div className="mt-2 px-3 py-2 bg-destructive/10 dark:bg-destructive/20 rounded-md border border-destructive/20">
                                <p className="text-xs font-medium text-destructive">{server.remarks}</p>
                            </div>
                        )}
                    </div>
                </PopoverContent>
            </Popover>
        );
    }

    return <Badge variant="outline">Unknown</Badge>;
}
