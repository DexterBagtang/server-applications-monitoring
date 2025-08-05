import React, { useState, useEffect, useRef } from "react";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog.js";
import { Button } from "@/components/ui/button.js";
import { Input } from "@/components/ui/input.js";
import { Label } from "@/components/ui/label.js";
import { AlertCircle, Loader2, Download, CheckCircle2 } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert.js";
import { Progress } from "@/components/ui/progress.js";
import axios from "axios";
import { formatFileSize } from "@/utils/fileUtils.js";

export function DbBackupDialog({ open, setOpen, server, application }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [credentials, setCredentials] = useState({
        db_name: application?.database_name || "",
        db_username: "",
        db_password: "",
    });
    const [progressData, setProgressData] = useState(null);
    const [isCompleted, setIsCompleted] = useState(false);

    const pollIntervalRef = useRef(null);

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setCredentials(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const resetState = () => {
        setError(null);
        setProgressData(null);
        setIsCompleted(false);
        setCredentials({
            db_name: application?.database_name || "",
            db_username: "",
            db_password: "",
        });
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
            pollIntervalRef.current = null;
        }
    };

    const handleDialogClose = () => {
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
            pollIntervalRef.current = null;
        }
        resetState();
        setOpen(false);
    };

    const pollProgress = async (progressKey) => {
        try {
            const response = await axios.get(`/downloads/progress/${progressKey}`);
            const data = response.data;

            setProgressData(data);

            if (data.status === 'complete') {
                setIsCompleted(true);
                setLoading(false);
                clearInterval(pollIntervalRef.current);
                pollIntervalRef.current = null;
            } else if (data.status === 'failed') {
                setError(data.error_message || 'Download failed');
                setLoading(false);
                clearInterval(pollIntervalRef.current);
                pollIntervalRef.current = null;
            }
        } catch (err) {
            console.error('Error polling progress:', err);
            setError('Failed to check download progress');
            setLoading(false);
            if (pollIntervalRef.current) {
                clearInterval(pollIntervalRef.current);
                pollIntervalRef.current = null;
            }
        }
    };

    const startProgressPolling = (progressKey) => {
        pollIntervalRef.current = setInterval(() => {
            pollProgress(progressKey);
        }, 2000);

        // Poll immediately
        pollProgress(progressKey);
    };

    const handleStartBackup = async () => {
        // Validate required fields
        if (!credentials.db_name.trim()) {
            setError("Database name is required");
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const url = `/database-dump/${server.id}/${credentials.db_name}`;

            const requestData = {
                db_type: application?.database_type || "mysql",
                ...(credentials.db_username && { db_username: credentials.db_username }),
                ...(credentials.db_password && { db_password: credentials.db_password }),
                ...(credentials.db_name && { db_name: credentials.db_name })
            };

            const response = await axios.post(url, requestData);

            if (response.status === 202) {
                const { progress_key } = response.data;
                startProgressPolling(progress_key);
            }
        } catch (err) {
            setError(err.response?.data?.error || "An unexpected error occurred");
            setLoading(false);
        }
    };

    const handleDownloadFile = () => {
        if (!progressData?.local_filename) return;

        const link = document.createElement('a');
        link.href = `/downloads/file/${progressData.local_filename}`;
        link.setAttribute('download', progressData.local_filename);
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    const getProgressPercentage = () => {
        if (!progressData?.total_size_mb || progressData.total_size_mb === 0) {
            return 0;
        }
        return Math.min(100, Math.round((progressData.downloaded_mb / progressData.total_size_mb) * 100));
    };

    const isFormValid = credentials.db_name.trim();
    const showProgress = loading && progressData && !isCompleted && !error;
    const showCredentialsForm = (!loading || error) && !isCompleted;

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (pollIntervalRef.current) {
                clearInterval(pollIntervalRef.current);
            }
        };
    }, []);


    const [isDbCredLoading, setIsDbCredLoading] = useState(false);

    useEffect(() => {
        if (open) {
            setIsDbCredLoading(true); // <-- show loading state
            axios.get(`/api/db-credentials/${application.id}`)
                .then(res => {
                    const data = res.data;
                    setCredentials(prev => ({
                        ...prev,
                        db_name: data.DB_DATABASE || prev.db_name,
                        db_username: data.DB_USERNAME || prev.db_username,
                        db_password: data.DB_PASSWORD || prev.db_password,
                    }));
                })
                .catch(err => {
                    console.warn("Could not auto-fill DB credentials:", err);
                    // silently fail; let user input manually
                })
                .finally(() => {
                    setIsDbCredLoading(false);
                });
        }
    }, [open]);


    return (
        <Dialog open={open} onOpenChange={handleDialogClose}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Database Backup</DialogTitle>
                    <DialogDescription>
                        {isDbCredLoading && (
                            <div className="flex items-center space-x-2 text-sm text-muted-foreground">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                <span>Fetching database credentials...</span>
                            </div>
                        )}
                        Download a backup of your database. Please provide the required database credentials.
                    </DialogDescription>
                </DialogHeader>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {/* Progress Section */}
                {showProgress && (
                    <div className="space-y-4">
                        <div className="flex items-center space-x-2">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            <span className="text-sm">
                                {progressData?.status === 'downloading' ? 'Downloading...' : 'Preparing backup...'}
                            </span>
                        </div>

                        {progressData?.total_size_mb > 0 && (
                            <>
                                <Progress value={getProgressPercentage()} className="w-full" />
                                <div className="flex justify-between text-xs text-muted-foreground">
                                    <span>
                                        {formatFileSize(progressData.downloaded_mb)} / {formatFileSize(progressData.total_size_mb)}
                                    </span>
                                    <span>{getProgressPercentage()}%</span>
                                </div>
                            </>
                        )}
                    </div>
                )}

                {/* Success Message */}
                {isCompleted && progressData && (
                    <Alert>
                        <CheckCircle2 className="h-4 w-4" />
                        <AlertDescription>
                            Backup completed successfully! File size: {formatFileSize(progressData.downloaded_mb)}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Credentials Form */}
                {showCredentialsForm && (
                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="db_name" className="text-right">
                                Database *
                            </Label>
                            <Input
                                id="db_name"
                                name="db_name"
                                value={credentials.db_name}
                                onChange={handleInputChange}
                                className="col-span-3"
                                placeholder="Enter database name"
                                required
                                disabled={isDbCredLoading}
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="db_username" className="text-right">
                                Username
                            </Label>
                            <Input
                                id="db_username"
                                name="db_username"
                                value={credentials.db_username}
                                onChange={handleInputChange}
                                className="col-span-3"
                                placeholder="Enter database username"
                                disabled={isDbCredLoading}

                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="db_password" className="text-right">
                                Password
                            </Label>
                            <Input
                                id="db_password"
                                name="db_password"
                                type="password"
                                value={credentials.db_password}
                                onChange={handleInputChange}
                                className="col-span-3"
                                placeholder="Enter database password"
                                disabled={isDbCredLoading}

                            />
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={handleDialogClose}>
                        {isCompleted ? 'Close' : 'Cancel'}
                    </Button>

                    {isCompleted ? (
                        <Button onClick={handleDownloadFile}>
                            <Download className="mr-2 h-4 w-4" />
                            Download File
                        </Button>
                    ) : (
                        <Button
                            onClick={handleStartBackup}
                            disabled={loading || !isFormValid || isDbCredLoading}
                        >
                            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Start Backup
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
