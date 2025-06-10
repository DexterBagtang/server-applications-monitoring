import React, { useState, useEffect } from 'react';
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
import { Badge } from '@/components/ui/badge';
import {
    Download,
    FileText,
    Loader2,
    CheckCircle,
    XCircle,
    Clock,
    AlertCircle
} from 'lucide-react';
import {formatFileSize} from "@/utils/fileUtils.js";

const FileDownloadManager = ({ server }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [currentStep, setCurrentStep] = useState('form'); // 'form', 'downloading', 'complete', 'error'
    const [formData, setFormData] = useState({
        remotePath: '/tmp/database.sql',
        localFilename: 'database.sql'
    });
    const [downloadProgress, setDownloadProgress] = useState(null);
    const [progressKey, setProgressKey] = useState('');
    const [error, setError] = useState('');
    const [downloadedFile, setDownloadedFile] = useState(null);

    // Poll for progress updates
    useEffect(() => {
        let interval;

        if (currentStep === 'downloading' && progressKey) {
            interval = setInterval(async () => {
                try {
                    const response = await fetch(`/downloads/progress/${progressKey}`);
                    const data = await response.json();

                    setDownloadProgress(data);

                    if (data.status === 'complete') {
                        setCurrentStep('complete');
                        setDownloadedFile({
                            filename: data.local_filename,
                            size: data.downloaded_mb,
                            downloadUrl: `/downloads/file/${data.local_filename}`
                        });
                    } else if (data.status === 'failed') {
                        setCurrentStep('error');
                        setError(data.error_message || 'Download failed');
                    }
                } catch (err) {
                    console.error('Error fetching progress:', err);
                }
            }, 1000); // Poll every second
        }

        return () => {
            if (interval) clearInterval(interval);
        };
    }, [currentStep, progressKey]);

    const handleStartDownload = async () => {
        try {
            setError('');
            setCurrentStep('downloading');

            const response = await fetch(`/downloads/servers/${server.id}/download`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({
                    remote_path: formData.remotePath,
                    local_filename: formData.localFilename
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to start download');
            }

            setProgressKey(data.progress_key);
            setDownloadProgress({
                status: 'pending',
                downloaded_mb: 0,
                progress_percentage: 0
            });
        } catch (err) {
            setCurrentStep('error');
            setError(err.message);
        }
    };

    const handleDownloadFile = () => {
        if (downloadedFile) {
            const link = document.createElement('a');
            link.href = downloadedFile.downloadUrl;
            link.download = downloadedFile.filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };

    const handleClose = () => {
        setIsOpen(false);
        // Reset state after a brief delay to avoid visual glitches
        setTimeout(() => {
            setCurrentStep('form');
            setDownloadProgress(null);
            setProgressKey('');
            setError('');
            setDownloadedFile(null);
        }, 300);
    };

    const getStatusIcon = (status) => {
        switch (status) {
            case 'pending':
                return <Clock className="h-4 w-4 text-yellow-500" />;
            case 'downloading':
                return <Loader2 className="h-4 w-4 text-blue-500 animate-spin" />;
            case 'complete':
                return <CheckCircle className="h-4 w-4 text-green-500" />;
            case 'failed':
                return <XCircle className="h-4 w-4 text-red-500" />;
            default:
                return <FileText className="h-4 w-4" />;
        }
    };

    const getStatusBadge = (status) => {
        const variants = {
            pending: 'secondary',
            downloading: 'default',
            complete: 'success',
            failed: 'destructive'
        };

        return (
            <Badge variant={variants[status] || 'secondary'} className="capitalize">
                {getStatusIcon(status)}
                <span className="ml-1">{status}</span>
            </Badge>
        );
    };



    const formatTimeRemaining = (seconds) => {
        if (!seconds) return 'Calculating...';
        if (seconds < 60) return `${seconds}s`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
        return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
    };

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

                {/* Form Step */}
                {currentStep === 'form' && (
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="remotePath">Remote File Path</Label>
                            <Input
                                id="remotePath"
                                value={formData.remotePath}
                                onChange={(e) => setFormData(prev => ({ ...prev, remotePath: e.target.value }))}
                                placeholder="/path/to/file.txt"
                            />
                            <p className="text-xs text-muted-foreground">
                                Full path to the file on the server
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="localFilename">Save as</Label>
                            <Input
                                id="localFilename"
                                value={formData.localFilename}
                                onChange={(e) => setFormData(prev => ({ ...prev, localFilename: e.target.value }))}
                                placeholder="filename.txt"
                            />
                            <p className="text-xs text-muted-foreground">
                                Local filename for the downloaded file
                            </p>
                        </div>
                    </div>
                )}

                {/* Downloading Step */}
                {currentStep === 'downloading' && downloadProgress && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">Download Status</span>
                            {getStatusBadge(downloadProgress.status)}
                        </div>

                        {downloadProgress.progress_percentage !== null && (
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span>Progress</span>
                                    <span>{downloadProgress?.progress_percentage?.toFixed(1)}%</span>
                                </div>
                                <Progress value={downloadProgress.progress_percentage || 0} />
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="text-muted-foreground">Downloaded:</span>
                                <div className="font-medium">
                                    {formatFileSize(downloadProgress.downloaded_mb || 0)}
                                </div>
                            </div>
                            {downloadProgress.total_size_mb && (
                                <div>
                                    <span className="text-muted-foreground">Total Size:</span>
                                    <div className="font-medium">
                                        {formatFileSize(downloadProgress.total_size_mb)}
                                    </div>
                                </div>
                            )}
                        </div>

                        {downloadProgress.estimated_time_remaining && (
                            <div className="text-sm">
                                <span className="text-muted-foreground">Time Remaining:</span>
                                <span className="ml-2 font-medium">
                  {formatTimeRemaining(downloadProgress.estimated_time_remaining)}
                </span>
                            </div>
                        )}
                    </div>
                )}

                {/* Complete Step */}
                {currentStep === 'complete' && downloadedFile && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2 text-green-600">
                            <CheckCircle className="h-5 w-5" />
                            <span className="font-medium">Download Complete!</span>
                        </div>

                        <div className="bg-green-50 border border-green-200 rounded-lg p-4 space-y-2">
                            <div className="flex justify-between">
                                <span className="text-sm text-green-700">Filename:</span>
                                <span className="text-sm font-medium text-green-800">
                  {downloadedFile.filename}
                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-green-700">Size:</span>
                                <span className="text-sm font-medium text-green-800">
                  {formatFileSize(downloadedFile.size)}
                </span>
                            </div>
                        </div>

                        <Button onClick={handleDownloadFile} className="w-full">
                            <Download className="h-4 w-4 mr-2" />
                            Download to Browser
                        </Button>
                    </div>
                )}

                {/* Error Step */}
                {currentStep === 'error' && (
                    <div className="space-y-4">
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                {error || 'An error occurred during download'}
                            </AlertDescription>
                        </Alert>

                        <Button
                            variant="outline"
                            onClick={() => setCurrentStep('form')}
                            className="w-full"
                        >
                            Try Again
                        </Button>
                    </div>
                )}

                <DialogFooter>
                    {currentStep === 'form' && (
                        <>
                            <Button
                                variant="outline"
                                onClick={handleClose}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleStartDownload}
                                disabled={!formData.remotePath || !formData.localFilename}
                            >
                                <Download className="h-4 w-4 mr-2" />
                                Start Download
                            </Button>
                        </>
                    )}

                    {currentStep === 'downloading' && (
                        <Button variant="outline" onClick={handleClose}>
                            Close (Download Continues)
                        </Button>
                    )}

                    {(currentStep === 'complete' || currentStep === 'error') && (
                        <Button onClick={handleClose}>
                            Close
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default FileDownloadManager;
