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
    AlertCircle,
    Folder,
    File,
    FolderOpen,
    ArrowLeft
} from 'lucide-react';
import { formatFileSize } from "@/utils/fileUtils.js";

const FileBrowser = ({ server, onSelectFile, currentPath, onClose }) => {
    const [files, setFiles] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [path, setPath] = useState(currentPath || '/');

    useEffect(() => {
        fetchFiles(path);
    }, [path]);

    const formatSftpFileSize = (bytes) => {
        if (!bytes || bytes === 0) return '0 B';

        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const base = 1024;
        const index = Math.floor(Math.log(bytes) / Math.log(base));
        const size = bytes / Math.pow(base, index);

        const formattedSize = index === 0 ? size.toString() : size.toFixed(1);
        return `${formattedSize} ${units[index]}`;
    };

    const fetchFiles = async (dirPath) => {
        setLoading(true);
        setError('');

        try {
            const { data } = await axios.get(`/downloads/servers/${server.id}/browse`, {
                params: { path: dirPath }
            });
            console.log(data.files)
            setFiles(data.files);
            setPath(data.current_path);
        } catch (err) {
            setError(err.response?.data?.error || 'Failed to browse files');
        } finally {
            setLoading(false);
        }
    };

    const handleFileClick = (file) => {
        if (file.is_directory) {
            setPath(file.path);
        } else {
            onSelectFile(file.path, file.name);
            onClose();
        }
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2 px-2 py-1.5 text-sm text-muted-foreground bg-muted rounded-md">
                <FolderOpen className="h-4 w-4 flex-shrink-0" />
                <span className="truncate font-mono text-xs">{path}</span>
            </div>

            {error && (
                <Alert variant="destructive" className="py-2">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription className="text-xs">{error}</AlertDescription>
                </Alert>
            )}

            <div className="border rounded-md max-h-64 overflow-y-auto text-sm">
                {loading ? (
                    <div className="flex items-center justify-center p-4">
                        <Loader2 className="h-4 w-4 animate-spin mr-2" />
                        <span>Loading files...</span>
                    </div>
                ) : (
                    <div className="divide-y">
                        {files.map((file, index) => (
                            <div
                                key={index}
                                className="flex items-center gap-2 p-2 hover:bg-accent cursor-pointer"
                                onClick={() => handleFileClick(file)}
                            >
                                <div className="flex-shrink-0 text-muted-foreground">
                                    {file.is_parent ? (
                                        <ArrowLeft className="h-4 w-4" />
                                    ) : file.is_directory ? (
                                        <Folder className="h-4 w-4 text-blue-500" />
                                    ) : (
                                        <File className="h-4 w-4" />
                                    )}
                                </div>

                                <div className="flex-1 min-w-0">
                                    <div className="truncate font-medium">{file.name}</div>
                                    {!file.is_directory && !file.is_parent && (
                                        <div className="text-xs text-muted-foreground flex gap-2">
                                            <span>{formatSftpFileSize(file.size)}</span>
                                            {file.modified && <span>{file.modified}</span>}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}

                        {files.length === 0 && !loading && (
                            <div className="p-4 text-center text-muted-foreground text-sm">
                                No files found
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};
const FileDownloadManager = ({ server }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [showBrowser, setShowBrowser] = useState(false);
    const [isDownloading, setIsDownloading] = useState(false);
    const [remotePath, setRemotePath] = useState('/');
    const [localFilename, setLocalFilename] = useState('');
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

    const handleFileSelect = (filePath, fileName) => {
        setRemotePath(filePath);
        // Extract filename from path if localFilename is not set or is default
        if (!localFilename || localFilename === 'database.sql') {
            setLocalFilename(fileName);
        }
        setShowBrowser(false);
    };

    const resetState = () => {
        setIsDownloading(false);
        setProgress(null);
        setProgressKey('');
        setError('');
        setDownloadComplete(false);
        setShowBrowser(false);
    };

    const handleClose = () => {
        setIsOpen(false);
        setTimeout(resetState, 300);
    };

    const isFormValid = remotePath.trim() && localFilename.trim();
    const showForm = !isDownloading && !downloadComplete && !error && !showBrowser;
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

            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        {showBrowser ? 'Browse Files' : `Download File from ${server.name}`}
                    </DialogTitle>
                </DialogHeader>

                {/* File Browser */}
                {showBrowser && (
                    <FileBrowser
                        server={server}
                        onSelectFile={handleFileSelect}
                        currentPath={remotePath}
                        onClose={() => setShowBrowser(false)}
                    />
                )}

                {/* Form */}
                {showForm && (
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="remotePath">Remote File Path</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="remotePath"
                                    value={remotePath}
                                    onChange={(e) => setRemotePath(e.target.value)}
                                    placeholder="/path/to/file.txt"
                                    className="flex-1"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setShowBrowser(true)}
                                >
                                    <Folder className="h-4 w-4" />
                                </Button>
                            </div>
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

                        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
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
                    {showBrowser && (
                        <Button variant="outline" onClick={() => setShowBrowser(false)}>
                            Back to Form
                        </Button>
                    )}

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
