import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogFooter
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Download,
    FileText,
    Loader2,
    CheckCircle,
    AlertCircle
} from 'lucide-react';
import { formatFileSize } from "@/utils/fileUtils.js";

const FileDownloadManager = ({ server }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [isDownloading, setIsDownloading] = useState(false);
    const [remotePath, setRemotePath] = useState('/tmp/database.sql');
    const [localFilename, setLocalFilename] = useState('database.sql');
    const [progress, setProgress] = useState(null);
    const [progressKey, setProgressKey] = useState('');
    const [error, setError] = useState('');
    const [downloadComplete, setDownloadComplete] = useState(false);

    // Configure axios defaults
    useEffect(() => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        }
    }, []);

    // Poll for progress updates
    useEffect(() => {
        if (!isDownloading || !progressKey) return;

        const interval = setInterval(async () => {
            try {
                const { data } = await axios.get(`/downloads/progress/${progressKey}`);
                setProgress(data);

                if (data.status === 'complete') {
                    setIsDownloading(false);
                    setDownloadComplete(true);
                } else if (data.status === 'failed') {
                    setIsDownloading(false);
                    setError(data.error_message || 'Download failed');
                }
            } catch (err) {
                console.error('Error fetching progress:', err);
            }
        }, 1000);

        return () => clearInterval(interval);
    }, [isDownloading, progressKey]);

    const startDownload = async () => {
        try {
            setError('');
            setIsDownloading(true);
            setDownloadComplete(false);

            const { data } = await axios.post(`/downloads/servers/${server.id}/download`, {
                remote_path: remotePath,
                local_filename: localFilename
            });

            setProgressKey(data.progress_key);
            setProgress({ status: 'pending', downloaded_mb: 0, progress_percentage: 0 });
        } catch (err) {
            setIsDownloading(false);
            setError(err.response?.data?.error || err.message || 'Failed to start download');
        }
    };

    const downloadFile = () => {
        if (progress?.local_filename) {
            const link = document.createElement('a');
            link.href = `/downloads/file/${progress.local_filename}`;
            link.download = progress.local_filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };

    const resetState = () => {
        setIsDownloading(false);
        setProgress(null);
        setProgressKey('');
        setError('');
        setDownloadComplete(false);
    };

    const handleClose = () => {
        setIsOpen(false);
        setTimeout(resetState, 300);
    };

    const isFormValid = remotePath.trim() && localFilename.trim();
    const showForm = !isDownloading && !downloadComplete && !error;
    const showProgress = isDownloading;
    const showSuccess = downloadComplete && !error;
    const showError = error && !isDownloading;

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="h-8">
                    <Download className="h-3 w-3 mr-2" />
                    Get file
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Download File from {server.name}
                    </DialogTitle>
                </DialogHeader>

                {/* Form */}
                {showForm && (
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="remotePath">Remote File Path</Label>
                            <Input
                                id="remotePath"
                                value={remotePath}
                                onChange={(e) => setRemotePath(e.target.value)}
                                placeholder="/path/to/file.txt"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="localFilename">Save as</Label>
                            <Input
                                id="localFilename"
                                value={localFilename}
                                onChange={(e) => setLocalFilename(e.target.value)}
                                placeholder="filename.txt"
                            />
                        </div>
                    </div>
                )}

                {/* Progress */}
                {showProgress && progress && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            <span className="text-sm font-medium">
                                {progress.status === 'pending' ? 'Starting download...' : 'Downloading...'}
                            </span>
                        </div>

                        {progress.progress_percentage !== null && (
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span>Progress</span>
                                    <span>{progress.progress_percentage?.toFixed(1)}%</span>
                                </div>
                                <Progress value={progress.progress_percentage || 0} />
                            </div>
                        )}

                        <div className="text-sm">
                            <span className="text-muted-foreground">Downloaded: </span>
                            <span className="font-medium">
                                {formatFileSize(progress.downloaded_mb || 0)}
                            </span>
                            {progress.total_size_mb && (
                                <>
                                    <span className="text-muted-foreground"> of </span>
                                    <span className="font-medium">
                                        {formatFileSize(progress.total_size_mb)}
                                    </span>
                                </>
                            )}
                        </div>
                    </div>
                )}

                {/* Success */}
                {showSuccess && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2 text-green-600">
                            <CheckCircle className="h-5 w-5" />
                            <span className="font-medium">Download Complete!</span>
                        </div>

                        <div className="rounded-lg p-4">
                            <div className="text-sm">
                                <div className="font-medium">{progress?.local_filename}</div>
                                <div className="text-green-700">
                                    {formatFileSize(progress?.downloaded_mb || 0)}
                                </div>
                            </div>
                        </div>

                        <Button onClick={downloadFile} className="w-full">
                            <Download className="h-4 w-4 mr-2" />
                            Download to Browser
                        </Button>
                    </div>
                )}

                {/* Error */}
                {showError && (
                    <div className="space-y-4">
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>

                        <Button variant="outline" onClick={resetState} className="w-full">
                            Try Again
                        </Button>
                    </div>
                )}

                <DialogFooter>
                    {showForm && (
                        <>
                            <Button variant="outline" onClick={handleClose}>
                                Cancel
                            </Button>
                            <Button onClick={startDownload} disabled={!isFormValid}>
                                <Download className="h-4 w-4 mr-2" />
                                Start Download
                            </Button>
                        </>
                    )}

                    {(showProgress || showSuccess || showError) && (
                        <Button variant="outline" onClick={handleClose}>
                            {showProgress ? 'Close (Download Continues)' : 'Close'}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default FileDownloadManager;
