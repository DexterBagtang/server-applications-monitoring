import {
    Dialog,
    DialogContent, DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {ScrollArea, ScrollBar} from "@/components/ui/scroll-area";
import {Separator} from "@/components/ui/separator";
import {useEffect, useState} from "react";
import {BadgeIcon, Loader2} from "lucide-react";
import {Tooltip, TooltipContent, TooltipTrigger} from "@/components/ui/tooltip.js";

export function LogApplicationDialog({logsData, open, setOpen, isFetching = false}) {
    const {logs, log_path} = logsData;
    const [logLines, setLogLines] = useState([]);

    useEffect(() => {
        if (logs) {
            const lines = typeof logs === 'string'
                ? logs.split('\n').filter(line => line.trim())
                : Array.isArray(logs)
                    ? logs.filter(line => line && line.trim())
                    : [];
            setLogLines(lines);
        } else {
            setLogLines([]);
        }
    }, [logs]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button className="hidden"/>
            </DialogTrigger>
            <DialogContent className="!max-w-4xl max-h-[80vh] w-full">
                <DialogHeader>
                    <DialogTitle>Application Logs</DialogTitle>
                    <DialogDescription>
                        {log_path}
                    </DialogDescription>
                    <DialogDescription>
                        Only the most recent 50 log entries are displayed.
                    </DialogDescription>
                </DialogHeader>

                <div className="relative h-[70vh] w-full overflow-hidden">
                    <ScrollArea className="h-[64vh] w-full pr-4 ">
                        <div className="space-y-2 min-w-max">
                            {isFetching ? (
                                <div className="flex flex-col items-center justify-center h-[60vh]">
                                    <Loader2 className="h-8 w-8 animate-spin text-gray-500"/>
                                    <p className="mt-4 text-gray-500">Fetching logs...</p>
                                </div>
                            ) : logLines.length === 0 ? (
                                <div className="flex flex-col items-center justify-center h-[60vh]">
                                    <p className="text-gray-500">No logs available</p>
                                </div>
                            ) : (
                                logLines.map((line, index) => (
                                    <div key={index} className="group">
                                        <div
                                            className="p-2 rounded-lg group-hover:bg-gray-100 dark:group-hover:bg-gray-800 transition-colors">
                                            <div className="overflow-x-auto">
                                                <pre
                                                    className="text-sm text-gray-700 dark:text-gray-300 whitespace-pre font-mono">
                                                    {line}
                                                </pre>
                                            </div>
                                        </div>
                                        {index < logLines.length - 1 && <Separator/>}
                                    </div>
                                ))
                            )}
                        </div>
                        <ScrollBar orientation="horizontal"/>
                        {/*<ScrollBar*/}
                        {/*    orientation="horizontal"*/}
                        {/*    className="h-3 bg-muted/30 dark:bg-muted/20 hover:bg-muted/50 dark:hover:bg-muted/40 rounded-full shadow-inner transition-all duration-300"*/}
                        {/*/>*/}

                    </ScrollArea>
                </div>
            </DialogContent>
        </Dialog>
    );
}
