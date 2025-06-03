import React, { useState } from "react";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog.js";
import { Button } from "@/components/ui/button.js";
import { Input } from "@/components/ui/input.js";
import { Label } from "@/components/ui/label.js";
import { AlertCircle, Loader2 } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert.js";
import axios from "axios";

export function DbBackupDialog({ open, setOpen, server, application }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [credentials, setCredentials] = useState({
        db_name: "",
        db_username: "",
        db_password: ""
    });

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setCredentials(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleBackup = async () => {
        setLoading(true);
        setError(null);

        try {
            // First try without credentials
            await downloadBackup();
        } catch (err) {
            if (err.response?.data?.need_credentials) {
                // If credentials are needed, show the form
                setError("Database name and credentials required. Please enter them below.");
            } else {
                setError(err.response?.data?.error || "An unexpected error occurred");
            }
        } finally {
            setLoading(false);
        }
    };

    const handleBackupWithCredentials = async () => {
        setLoading(true);
        setError(null);

        try {
            await downloadBackup(credentials);
            setOpen(false);
        } catch (err) {
            setError(err.response?.data?.error || "An unexpected error occurred");
        } finally {
            setLoading(false);
        }
    };

    const downloadBackup = async (creds = null) => {
        // Determine which database to use
        const database = creds?.db_name || application.database_name || "";

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

        if (creds) {
            // If we have credentials, add them as query parameters
            const params = new URLSearchParams();
            if (creds.db_username) params.append('db_username', creds.db_username);
            if (creds.db_password) params.append('db_password', creds.db_password);
            if (params.toString()) {
                url += `?${params.toString()}`;
            }
        }

        // Use axios to get the file as a blob
        const response = await axios.get(url, {
            responseType: 'blob'
        });

        // Create a download link and trigger it
        const downloadUrl = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = downloadUrl;

        // Get filename from Content-Disposition header or create a default one
        const contentDisposition = response.headers['content-disposition'];
        let filename = 'database_backup.sql';

        if (contentDisposition) {
            const filenameMatch = contentDisposition.match(/filename="(.+)"/);
            if (filenameMatch && filenameMatch[1]) {
                filename = filenameMatch[1];
            }
        }

        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
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

                {error && (error.includes("credentials required") || error.includes("Database name and credentials required")) ? (
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
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>

                    {error && (error.includes("credentials required") || error.includes("Database name and credentials required")) ? (
                        <Button onClick={handleBackupWithCredentials} disabled={loading}>
                            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Download Backup
                        </Button>
                    ) : (
                        <Button onClick={handleBackup} disabled={loading}>
                            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Download Backup
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
