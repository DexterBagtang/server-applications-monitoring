import React, { useState, useEffect, useRef } from "react";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog.js";
import { Button } from "@/components/ui/button.js";
import { Input } from "@/components/ui/input.js";
import { Label } from "@/components/ui/label.js";
import { AlertCircle, Loader2, Download, CheckCircle2 } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert.js";
import { Progress } from "@/components/ui/progress.js";
import axios from "axios";
import {formatFileSize} from "@/utils/fileUtils.js";

export function DbBackupDialog({ open, setOpen, server, application }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [credentials, setCredentials] = useState({
        db_name: "",
        db_username: "",
        db_password: ""
    });
    const [downloadProgress, setDownloadProgress] = useState(null);
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
        setDownloadProgress(null);
        setProgressData(null);
        setIsCompleted(false);
        setCredentials({
            db_name: "",
            db_username: "",
            db_password: ""
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
                if (pollIntervalRef.current) {
                    clearInterval(pollIntervalRef.current);
                    pollIntervalRef.current = null;
                }
            } else if (data.status === 'failed') {
                setError(data.error_message || 'Download failed');
                setLoading(false);
                if (pollIntervalRef.current) {
                    clearInterval(pollIntervalRef.current);
                    pollIntervalRef.current = null;
                }
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
        // Poll every 2 seconds
        pollIntervalRef.current = setInterval(() => {
            pollProgress(progressKey);
        }, 2000);

        // Also poll immediately
        pollProgress(progressKey);
    };

    const handleBackup = async () => {
        setLoading(true);
        setError(null);
        resetState();

        try {
            // First try without credentials
            await downloadBackup();
        } catch (err) {
            if (err.response?.data?.need_credentials) {
                // If credentials are needed, show the form
                setError("Database name and credentials required. Please enter them below.");
                setLoading(false);
            } else {
                setError(err.response?.data?.error || "An unexpected error occurred");
                setLoading(false);
            }
        }
    };

    const handleBackupWithCredentials = async () => {
        setLoading(true);
        setError(null);

        try {
            await downloadBackup(credentials);
        } catch (err) {
            setError(err.response?.data?.error || "An unexpected error occurred");
            setLoading(false);
        }
    };

    const downloadBackup = async (creds = null) => {
        // Determine which database to use
        const database = creds?.db_name || application?.database_name || "";

        // Check if database is empty
        if (!database) {
            throw {
                response: {
                    data: {
                        error: "Database name is required",
                        need_credentials: true
                    }
                }
            };
        }

        // Create the URL
        let url = `/mysql-dump/${server.id}/${database}`;

        const requestData = {};
        if (creds) {
            if (creds.db_username) requestData.db_username = creds.db_username;
            if (creds.db_password) requestData.db_password = creds.db_password;
            if (creds.db_name) requestData.db_name = creds.db_name;
        }

        // Make POST request to start the backup process
        const response = await axios.post(url, requestData);

        if (response.status === 202) {
            // Download started, begin polling
            const { progress_key, progress_id } = response.data;
            setDownloadProgress(response.data);
            startProgressPolling(progress_key);
        }
    };

    const handleDownloadFile = () => {
        if (!progressData?.local_filename) return;

        // Construct the file URL
        const fileUrl = `/downloads/file/${progressData.local_filename}`;

        // Create a link and trigger download
        const link = document.createElement('a');
        link.href = fileUrl;

        // Use the filename if you want to force it (optional)
        link.setAttribute('download', progressData.local_filename);

        document.body.appendChild(link);
        link.click();
        link.remove();
    };


    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (pollIntervalRef.current) {
                clearInterval(pollIntervalRef.current);
            }
        };
    }, []);

    const getProgressPercentage = () => {
        if (!progressData || !progressData.total_size_mb || progressData.total_size_mb === 0) {
            return 0;
        }
        return Math.min(100, Math.round((progressData.downloaded_mb / progressData.total_size_mb) * 100));
    };


    return (
        <Dialog open={open} onOpenChange={handleDialogClose}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Database Backup</DialogTitle>
                    <DialogDescription>
                        Download a backup of your database.
                    </DialogDescription>
                </DialogHeader>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {/* Show progress when download is in progress */}
                {downloadProgress && !isCompleted && !error && (
                    <div className="space-y-4">
                        <div className="flex items-center space-x-2">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            <span className="text-sm">
                                {progressData?.status === 'downloading' ? 'Downloading...' : 'Preparing backup...'}
                            </span>
                        </div>

                        {progressData && progressData.total_size_mb > 0 && (
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

                {/* Show success message when completed */}
                {isCompleted && progressData && (
                    <Alert>
                        <CheckCircle2 className="h-4 w-4" />
                        <AlertDescription>
                            Backup completed successfully! File size: {formatFileSize(progressData.downloaded_mb)}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Show credentials form when needed */}
                {error && (error.includes("credentials required") || error.includes("Database name and credentials required")) && !downloadProgress ? (
                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="db_name" className="text-right">
                                Database
                            </Label>
                            <Input
                                id="db_name"
                                name="db_name"
                                value={credentials.db_name}
                                onChange={handleInputChange}
                                className="col-span-3"
                                placeholder="Enter database name"
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
                            />
                        </div>
                    </div>
                ) : null}

                <DialogFooter>
                    <Button variant="outline" onClick={handleDialogClose}>
                        {isCompleted ? 'Close' : 'Cancel'}
                    </Button>

                    {isCompleted ? (
                        <Button onClick={handleDownloadFile}>
                            <Download className="mr-2 h-4 w-4" />
                            Download File
                        </Button>
                    ) : error && (error.includes("credentials required") || error.includes("Database name and credentials required")) ? (
                        <Button onClick={handleBackupWithCredentials} disabled={loading}>
                            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Start Backup
                        </Button>
                    ) : !downloadProgress ? (
                        <Button onClick={handleBackup} disabled={loading}>
                            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Start Backup
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
