import {Card, CardContent, CardDescription, CardHeader, CardTitle} from "@/components/ui/card.js";
import {Badge} from "@/components/ui/badge.js";
import {formatDistanceToNow} from "date-fns";
import {Button} from "@/components/ui/button.js";
import axios from "axios";
import {
    AlertCircle,
    CheckCircle,
    Database,
    Edit2Icon,
    Edit3Icon,
    ExternalLink, EyeIcon,
    MoreVertical,
    RefreshCw,
    Settings,
    Terminal,
    Trash2, XCircle,Eye
} from "lucide-react";
import AddApplicationDialog from "@/features/applications/AddApplicationDialog.jsx";
import {ServerTerminal} from "@/features/servers/show/ServerTerminal.jsx";
import {
    DropdownMenu,
    DropdownMenuContent, DropdownMenuItem,
    DropdownMenuLabel, DropdownMenuSeparator,
    DropdownMenuTrigger
} from "@/components/ui/dropdown-menu.js";
import EditApplicationDialog from "@/features/applications/EditApplicationDialog.jsx";
import {useState} from "react";
import DeleteApplicationDialog from "@/features/applications/DeleteApplicationDialog.jsx";
import {Label} from "@/components/ui/label.js";
import {Input} from "@/components/ui/input.js";
import {router} from "@inertiajs/react";
import {LogApplicationDialog} from "@/features/applications/show/LogApplicationDialog.jsx";
import {DbBackupDialog} from "@/features/applications/show/DbBackupDialog.jsx";
import FileDownloadManager from "@/features/applications/show/FileDownloadManager.jsx";

export default function ApplicationsCard({applications = [], server}) {
    // const [openOption, setOpenOption] = useState(false)
    const [editDialog, setEditDialog] = useState(false);
    const [deleteDialog, setDeleteDialog] = useState(false);
    const [selectedApp, setSelectedApp] = useState({});
    const [logDialog, setLogDialog] = useState(false);
    const [logResult, setLogResult] = useState('');
    const [loading, setLoading] = useState(false);
    const [dbBackupDialog, setDbBackupDialog] = useState(false);

    const logFormatter = Intl.NumberFormat('en',{
        notation:'compact',
        compactDisplay:'short'
    })

    // Format uptime in seconds to a human-readable format
    const formatUptime = (seconds) => {
        if (!seconds) return 'N/A';

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);

        if (days > 0) {
            return `${days}d ${hours}h`;
        } else if (hours > 0) {
            return `${hours}h ${minutes}m`;
        } else {
            return `${minutes}m`;
        }
    }


    function handleEdit(application){
        setSelectedApp(application)
        setEditDialog(true)
    }

    function handleDelete(application){
        setSelectedApp(application)
        setDeleteDialog(true)
    }

    async function handleLogAccess(id,logPath) {
        setLogResult('');
        setLogDialog(true);
        setLoading(true);
        try {
            const res = await axios.get(`/applications/${id}/fetch-logs`,{
                params: {
                    log_path: logPath,
                }
            });
            setLogResult(res.data);
        }finally {
            setLoading(false);
        }
    }

    function handleDbBackup(application) {
        setSelectedApp(application);
        setDbBackupDialog(true);
    }

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <h2 className="text-xl font-semibold">Deployed Applications ({applications.length})</h2>
                <div className="flex gap-2">
                    {/*<Button variant="outline" size="sm" className="h-8 gap-1">*/}
                    {/*    <RefreshCw className="h-3 w-3" /> Refresh*/}
                    {/*</Button>*/}
                    <AddApplicationDialog serverId={server.id}/>
                </div>
            </div>

            {applications.length === 0 ? (
                <div className="text-center py-8 border rounded-lg">
                    <p className="text-muted-foreground">No applications found</p>
                    <AddApplicationDialog serverId={server.id} triggerClassName="mt-4"/>
                </div>
            ) : (
                <div className="space-y-2">
                    {applications.map((application) => (
                            <Card key={application.id} className="overflow-hidden hover:shadow-md transition-shadow">
                                <CardHeader className="pb-4">
                                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3">
                                        <div className="space-y-1">
                                            <CardTitle className="text-lg flex items-center gap-2">
                                                {application.name}
                                                <StatusBadge status={application.status} />
                                            </CardTitle>
                                            <CardDescription className="flex flex-wrap items-center gap-2 text-sm">
                                                <span className="font-medium text-foreground">{application.type}</span>
                                                {application.framework_version && (
                                                    <Badge variant="secondary">v{application.framework_version}</Badge>
                                                )}
                                                {application.last_deployed_at && (
                                                    <span className="text-muted-foreground">
                                                      Deployed {formatDistanceToNow(new Date(application.last_deployed_at))} ago
                                                    </span>
                                                )}
                                            </CardDescription>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" onClick={() => handleEdit(application)}>
                                                <Edit3Icon />
                                            </Button>

                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="ml-2"
                                                onClick={() => handleDelete(application)}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>

                                    </div>
                                </CardHeader>

                                <CardContent className="pt-0 grid gap-6">
                                    {/* Main Application Details */}
                                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                        {/* Column 1: Deployment Info */}
                                        <div className="space-y-4">
                                            <div>
                                                <p className="text-xs text-muted-foreground mb-2">Application URL</p>
                                                <div className="flex items-center gap-2">
                                                    <a
                                                        href={application.app_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-sm hover:underline flex items-center gap-1 break-all"
                                                    >
                                                        {application.app_url || 'Not configured'}
                                                        <ExternalLink className="h-3 w-3 flex-shrink-0"/>
                                                    </a>
                                                </div>
                                            </div>

                                            <div>
                                                <p className="text-xs text-muted-foreground mb-2">Deployment Path</p>
                                                <div className="p-2 rounded bg-muted/50 overflow-x-auto">
                                                    <p className="text-sm font-mono whitespace-nowrap">
                                                        {application.path}
                                                    </p>
                                                </div>
                                            </div>

                                            {application.notes && (
                                                <div>
                                                    <p className="text-xs text-muted-foreground mb-2">Notes</p>
                                                    <p className="text-sm text-muted-foreground whitespace-pre-line">
                                                        {application.notes}
                                                    </p>
                                                </div>
                                            )}
                                        </div>

                                        {/* Column 2: Tech Stack */}
                                        <div className="space-y-4">
                                            <div>
                                                <p className="text-xs text-muted-foreground mb-2">Technology Stack</p>
                                                <div className="flex flex-wrap gap-2">
                                                    <Badge variant="outline">
                                                        {application.language} {application.language_version}
                                                    </Badge>
                                                    {application.web_server && (
                                                        <Badge variant="outline">{application.web_server}</Badge>
                                                    )}
                                                    {application.database_type && (
                                                        <Badge variant="outline">{application.database_type}</Badge>
                                                    )}
                                                </div>
                                            </div>


                                            <div className="space-y-3">
                                                <div>
                                                    <Label className="text-xs text-muted-foreground mb-2 block">
                                                        Access Log
                                                    </Label>
                                                    <div className="relative">
                                                        <Input
                                                            readOnly={true}
                                                            value={application.access_log_path || 'No log path added'}
                                                            className={application.access_log_path ? "pr-12" : ""}
                                                        />
                                                        {application.access_log_path && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="absolute right-1 top-1/2 -translate-y-1/2 h-8 w-8 p-0"
                                                                onClick={()=>handleLogAccess(application.id,application.access_log_path)}
                                                            >
                                                                <Eye size={16} />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                                <div>
                                                    <Label className="text-xs text-muted-foreground mb-2 block">
                                                        Error Log
                                                    </Label>
                                                    <div className="relative">
                                                        <Input
                                                            readOnly={true}
                                                            value={application.error_log_path || 'No log path added'}
                                                            className={application.error_log_path ? "pr-12" : ""}
                                                        />
                                                        {application.error_log_path && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="absolute right-1 top-1/2 -translate-y-1/2 h-8 w-8 p-0"
                                                                onClick={()=>handleLogAccess(application.id,application.error_log_path)}
                                                            >
                                                                <Eye size={16} />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Column 3: Quick Actions */}
                                        <div className="space-y-4">
                                            <div>
                                                <p className="text-xs text-muted-foreground mb-2">Quick Actions</p>
                                                <div className="grid grid-cols-2 gap-3">
                                                    <Button variant="outline" size="sm" className="h-8">
                                                        <RefreshCw className="h-3 w-3 mr-2"/> Restart
                                                    </Button>
                                                    <ServerTerminal
                                                        server={server}
                                                        directory={application.path}
                                                        label="Console"
                                                    />
                                                    <Button variant="outline" size="sm" className="h-8">
                                                        <Settings className="h-3 w-3 mr-2"/> Env Vars
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-8"
                                                        onClick={() => handleDbBackup(application)}
                                                    >
                                                        <Database className="h-3 w-3 mr-2"/> DB Backup
                                                    </Button>

                                                </div>
                                            </div>

                                            <div>
                                                <p className="text-xs text-muted-foreground mb-2">Statistics</p>
                                                <div className="grid grid-cols-3 gap-3 text-center">
                                                    <div className="p-2 bg-muted/50 rounded">
                                                        <p className="text-xs">Uptime</p>
                                                        <p className="text-sm font-medium">
                                                            {formatUptime(application.latest_metric?.uptime)}
                                                        </p>
                                                    </div>
                                                    <div className="p-2 bg-muted/50 rounded">
                                                        <p className="text-xs">Requests</p>
                                                        <p className="text-sm font-medium">
                                                            {logFormatter.format(application.latest_metric?.request_count)}
                                                        </p>
                                                    </div>
                                                    <div className="p-2 bg-muted/50 rounded">
                                                        <p className="text-xs">Errors</p>
                                                        <p className="text-sm font-medium">
                                                            {logFormatter.format(application.latest_metric?.error_count)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                    ))}

                    <EditApplicationDialog application={selectedApp} open={editDialog} setOpen={setEditDialog} />
                    <DeleteApplicationDialog application={selectedApp} open={deleteDialog} setOpen={setDeleteDialog} />
                    <LogApplicationDialog logsData={logResult} open={logDialog} setOpen={setLogDialog} isFetching={loading} />
                    <DbBackupDialog server={server} application={selectedApp} open={dbBackupDialog} setOpen={setDbBackupDialog} />
                </div>
            )}
        </div>
    );
}


const StatusBadge = ({ status }) => {
    const statusConfig = {
        up: {
            icon: CheckCircle,
            className: 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 border-green-200 dark:border-green-800'
        },
        down: {
            icon: XCircle,
            className: 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400 border-red-200 dark:border-red-800'
        },
        unknown: {
            icon: AlertCircle,
            className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800'
        }
    };

    const config = statusConfig[status] || statusConfig.unknown;
    const Icon = config.icon;

    return (
        <Badge className={`${config.className} flex items-center gap-1`}>
            <Icon className="w-3 h-3" />
            {status}
        </Badge>
    );
};
