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
    ArrowLeft,
    Upload,
    Server,
    ChevronRight,
    X,
    RefreshCw,
    Clock,
    HardDrive
} from 'lucide-react';
import { formatFileSize } from "@/utils/fileUtils.js";
import {Card, CardContent} from "@/components/ui/card.js";

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
        <div className="space-y-4">
            {/* Header with server info and current path */}
            <div className="bg-muted/50 rounded-lg p-3 border">
                <div className="flex items-center gap-2 mb-2">
                    <Server className="h-4 w-4 text-primary" />
                    <span className="font-medium">{server.name}</span>
                    <span className="text-muted-foreground text-sm">({server.ip_address})</span>
                </div>
                <div className="flex items-center gap-2 text-sm">
                    <FolderOpen className="h-4 w-4 text-muted-foreground" />
                    <span className="font-mono bg-background px-2 py-1 rounded border text-foreground">{path}</span>
                </div>
            </div>

            {error && (
                <Alert variant="destructive" className="animate-in slide-in-from-top-2">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription className="text-sm">{error}</AlertDescription>
                    <Button
                        variant="outline"
                        size="sm"
                        className="ml-auto"
                        onClick={() => fetchFiles(path)}
                    >
                        <RefreshCw className="h-3 w-3" />
                    </Button>
                </Alert>
            )}

            <div className="border rounded-lg overflow-hidden shadow-sm">
                {loading ? (
                    <div className="flex items-center justify-center p-8">
                        <Loader2 className="h-5 w-5 animate-spin mr-3 text-primary" />
                        <span className="text-sm text-muted-foreground">Loading files...</span>
                    </div>
                ) : (
                    <div className="max-h-72 overflow-y-auto">
                        {files.map((file, index) => (
                            <div
                                key={index}
                                className="flex items-center gap-3 p-3 hover:bg-accent cursor-pointer transition-colors border-b last:border-b-0 group"
                                onClick={() => handleFileClick(file)}
                            >
                                <div className="flex-shrink-0">
                                    {file.is_parent ? (
                                        <div className="p-1 rounded bg-muted group-hover:bg-muted/70 transition-colors">
                                            <ArrowLeft className="h-4 w-4 text-muted-foreground" />
                                        </div>
                                    ) : file.is_directory ? (
                                        <div className="p-1 rounded bg-primary/10 group-hover:bg-primary/20 transition-colors">
                                            <Folder className="h-4 w-4 text-primary" />
                                        </div>
                                    ) : (
                                        <div className="p-1 rounded bg-green-500/10 group-hover:bg-green-500/20 transition-colors">
                                            <File className="h-4 w-4 text-green-600 dark:text-green-400" />
                                        </div>
                                    )}
                                </div>

                                <div className="flex-1 min-w-0">
                                    <div className="font-medium truncate group-hover:text-primary transition-colors">
                                        {file.name}
                                    </div>
                                    {!file.is_directory && !file.is_parent && (
                                        <div className="text-xs text-muted-foreground flex items-center gap-3 mt-1">
                                            <span className="flex items-center gap-1">
                                                <HardDrive className="h-3 w-3" />
                                                {formatSftpFileSize(file.size)}
                                            </span>
                                            {file.modified && (
                                                <span className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />
                                                    {file.modified}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <ChevronRight className="h-4 w-4 text-muted-foreground group-hover:text-primary transition-colors" />
                            </div>
                        ))}

                        {files.length === 0 && !loading && (
                            <div className="p-8 text-center">
                                <Folder className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                                <p className="text-muted-foreground text-sm">No files found in this directory</p>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};

const ServerSelector = ({ servers, selectedServerId, onSelectServer }) => {
    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <Upload className="h-4 w-4 text-primary" />
                <Label className="font-medium">Choose Target Server</Label>
            </div>


            <div className="grid grid-cols-3 gap-1 max-h-64 overflow-y-auto">
                {servers.map((server) => (
                    <Card
                        key={server.id}
                        onClick={() => onSelectServer(server.id)}
                        className={`cursor-pointer transition-all ${
                            selectedServerId === server.id
                                ? 'border-primary ring-1 ring-primary bg-accent'
                                : 'hover:bg-accent/50'
                        }`}
                    >
                        <CardContent className="flex items-start gap-3">
                            <div
                                className={`p-2 rounded-lg ${
                                    selectedServerId === server.id ? 'bg-primary/10' : 'bg-muted'
                                }`}
                            >
                                <Server
                                    className={`h-4 w-4 ${
                                        selectedServerId === server.id ? 'text-primary' : 'text-muted-foreground'
                                    }`}
                                />
                            </div>

                            <div className="flex-1 min-w-0">
                                <div className="font-medium truncate">{server.name}</div>
                                <div className="text-sm text-muted-foreground truncate">{server.ip_address}</div>
                            </div>

                            {selectedServerId === server.id && (
                                <div className="bg-primary/10 p-1 rounded-full">
                                    <CheckCircle className="h-4 w-4 text-primary" />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>

        </div>
    );
};

const FileDownloadManager = ({ server, servers }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [showBrowser, setShowBrowser] = useState(false);
    const [showServerSelector, setShowServerSelector] = useState(false);
    const [isDownloading, setIsDownloading] = useState(false);
    const [isTransferring, setIsTransferring] = useState(false);
    const [remotePath, setRemotePath] = useState('/');
    const [localFilename, setLocalFilename] = useState('');
    const [targetServerId, setTargetServerId] = useState(null);
    const [targetPath, setTargetPath] = useState('/');
    const [progress, setProgress] = useState(null);
    const [progressKey, setProgressKey] = useState('');
    const [error, setError] = useState('');
    const [downloadComplete, setDownloadComplete] = useState(false);
    const [transferComplete, setTransferComplete] = useState(false);
    const [actionType, setActionType] = useState('');

    // Configure axios defaults
    useEffect(() => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        }
    }, []);

    // Poll for progress updates
    useEffect(() => {
        if ((!isDownloading && !isTransferring) || !progressKey) return;

        const interval = setInterval(async () => {
            try {
                const endpoint = actionType === 'transfer'
                    ? `/uploads/progress/${progressKey}`
                    : `/downloads/progress/${progressKey}`;

                const { data } = await axios.get(endpoint);
                setProgress(data);

                if (data.status === 'complete') {
                    if (actionType === 'download') {
                        setIsDownloading(false);
                        setDownloadComplete(true);
                    } else {
                        setIsTransferring(false);
                        setTransferComplete(true);
                    }
                } else if (data.status === 'failed') {
                    if (actionType === 'download') {
                        setIsDownloading(false);
                    } else {
                        setIsTransferring(false);
                    }
                    setError(data.error_message || 'Operation failed');
                }
            } catch (err) {
                console.error('Error fetching progress:', err);
            }
        }, 1000);

        return () => clearInterval(interval);
    }, [isDownloading, isTransferring, progressKey, actionType]);

    const startDownload = async () => {
        try {
            setError('');
            setIsDownloading(true);
            setDownloadComplete(false);
            setActionType('download');

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

    const startTransfer = async () => {
        try {
            setError('');
            setIsTransferring(true);
            setTransferComplete(false);
            setActionType('transfer');

            const { data } = await axios.post(`/uploads/servers/${targetServerId}/upload`, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                },
                remote_path: targetPath,
                file: {
                    path: remotePath,
                    name: localFilename
                },
                overwrite: true
            });

            setProgressKey(data.progress_key);
            setProgress({ status: 'pending', uploaded_mb: 0, progress_percentage: 0 });
        } catch (err) {
            setIsTransferring(false);
            setError(err.response?.data?.error || err.message || 'Failed to start transfer');
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
        if (!localFilename || localFilename === 'database.sql') {
            setLocalFilename(fileName);
        }
        setShowBrowser(false);
    };

    const resetState = () => {
        setIsDownloading(false);
        setIsTransferring(false);
        setProgress(null);
        setProgressKey('');
        setError('');
        setDownloadComplete(false);
        setTransferComplete(false);
        setShowBrowser(false);
        setShowServerSelector(false);
        setActionType('');
    };

    const handleClose = () => {
        setIsOpen(false);
        setTimeout(resetState, 300);
    };

    const isFormValid = remotePath.trim() && localFilename.trim();
    const isTransferFormValid = isFormValid && targetServerId && targetPath.trim();
    const showForm = !isDownloading && !downloadComplete && !error && !showBrowser && !showServerSelector && !isTransferring && !transferComplete;
    const showProgress = isDownloading || isTransferring;
    const showSuccess = (downloadComplete || transferComplete) && !error;
    const showError = error && !isDownloading && !isTransferring;

    const getDialogTitle = () => {
        if (showBrowser) return `Browse Files - ${server.name}`;
        if (showServerSelector) return 'Transfer File';
        if (showProgress) return actionType === 'download' ? 'Downloading File...' : 'Transferring File...';
        if (showSuccess) return actionType === 'download' ? 'Download Complete!' : 'Transfer Complete!';
        if (showError) return 'Operation Failed';
        return `File Manager - ${server.name}`;
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="h-8 transition-all hover:scale-105">
                    <Download className="h-3 w-3 mr-2" />
                    Get File
                </Button>
            </DialogTrigger>

            <DialogContent className="!sm:max-w-2xl !max-h-[90vh] !max-w-4xl overflow-hidden flex flex-col">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="flex items-center gap-2 text-lg">
                        <div className="p-2 bg-primary/10 rounded-lg">
                            <FileText className="h-5 w-5 text-primary" />
                        </div>
                        {getDialogTitle()}
                    </DialogTitle>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto">
                    {/* File Browser */}
                    {showBrowser && (
                        <div className="py-4">
                            <FileBrowser
                                server={server}
                                onSelectFile={handleFileSelect}
                                currentPath={remotePath}
                                onClose={() => setShowBrowser(false)}
                            />
                        </div>
                    )}

                    {/* Server Selector */}
                    {showServerSelector && (
                        <div className="py-4 space-y-6">
                            <ServerSelector
                                servers={servers?.filter(s => s.id !== server.id)}
                                selectedServerId={targetServerId}
                                onSelectServer={(id) => setTargetServerId(id)}
                            />

                            {targetServerId && (
                                <div className="space-y-3 p-4 bg-muted/50 rounded-lg border">
                                    <div className="flex items-center gap-2">
                                        <FolderOpen className="h-4 w-4 text-primary" />
                                        <Label htmlFor="targetPath" className="font-medium">Destination Directory</Label>
                                    </div>
                                    <Input
                                        id="targetPath"
                                        value={targetPath}
                                        onChange={(e) => setTargetPath(e.target.value)}
                                        placeholder="/path/to/destination/"
                                    />

                                    {/* File preview */}
                                    <div className="text-sm bg-background p-2 rounded border">
                                        <strong>File:</strong> {localFilename} → {servers.find(s => s.id === targetServerId)?.name}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Main Form */}
                    {showForm && (
                        <div className="py-6 space-y-6">
                            {/* Source Server Info */}
                            <div className="bg-muted/30 p-4 rounded-lg border">
                                <div className="flex items-center gap-2 mb-2">
                                    <Server className="h-4 w-4 text-green-600 dark:text-green-400" />
                                    <span className="font-medium">Source Server</span>
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {server.name} ({server.ip_address})
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="space-y-3">
                                    <Label htmlFor="remotePath" className="flex items-center gap-2 font-medium">
                                        <File className="h-4 w-4" />
                                        Source File Path
                                    </Label>
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
                                            className="px-3"
                                        >
                                            <Folder className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <Label htmlFor="localFilename" className="flex items-center gap-2 font-medium">
                                        <FileText className="h-4 w-4" />
                                        Local Filename
                                    </Label>
                                    <Input
                                        id="localFilename"
                                        value={localFilename}
                                        onChange={(e) => setLocalFilename(e.target.value)}
                                        placeholder="filename.txt"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Progress Display */}
                    {showProgress && progress && (
                        <div className="py-6 space-y-6">
                            <div className="text-center">
                                <div className="bg-primary/10 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                                    <Loader2 className="h-8 w-8 animate-spin text-primary" />
                                </div>
                                <h3 className="text-lg font-medium mb-2">
                                    {progress.status === 'pending' ?
                                        (actionType === 'download' ? 'Preparing download...' : 'Preparing transfer...') :
                                        (actionType === 'download' ? 'Downloading file...' : 'Transferring file...')}
                                </h3>
                                <p className="text-muted-foreground">
                                    {actionType === 'download' ? 'Please wait while we download your file' : 'Please wait while we transfer your file'}
                                </p>
                            </div>

                            {progress.progress_percentage !== null && (
                                <div className="space-y-3">
                                    <div className="flex justify-between text-sm font-medium">
                                        <span>Progress</span>
                                        <span className="text-primary">{progress.progress_percentage?.toFixed(1)}%</span>
                                    </div>
                                    <Progress value={progress.progress_percentage || 0} className="h-2" />
                                </div>
                            )}

                            <div className="bg-muted/50 p-4 rounded-lg">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        {actionType === 'download' ? 'Downloaded:' : 'Transferred:'}
                                    </span>
                                    <span className="font-medium">
                                        {formatFileSize(actionType === 'download' ? (progress.downloaded_mb || 0) : (progress.uploaded_mb || 0))}
                                        {progress.total_size_mb && (
                                            <span className="text-muted-foreground"> of {formatFileSize(progress.total_size_mb)}</span>
                                        )}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Success Display */}
                    {showSuccess && (
                        <div className="py-6 space-y-6">
                            <div className="text-center">
                                <div className="bg-green-500/10 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                                    <CheckCircle className="h-8 w-8 text-green-600 dark:text-green-400" />
                                </div>
                                <h3 className="text-lg font-medium mb-2">
                                    {actionType === 'download' ? 'Download Complete!' : 'Transfer Complete!'}
                                </h3>
                                <p className="text-muted-foreground">
                                    Your file has been successfully {actionType === 'download' ? 'downloaded' : 'transferred'}
                                </p>
                            </div>

                            <div className="bg-green-500/5 border border-green-500/20 rounded-lg p-4">
                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-sm text-muted-foreground">Filename:</span>
                                        <span className="font-medium">{actionType === 'download' ? progress?.local_filename : localFilename}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm text-muted-foreground">Size:</span>
                                        <span className="font-medium text-green-600 dark:text-green-400">
                                            {formatFileSize(actionType === 'download' ? (progress?.downloaded_mb || 0) : (progress?.uploaded_mb || 0))}
                                        </span>
                                    </div>
                                    {actionType === 'transfer' && targetServerId && (
                                        <div className="flex justify-between">
                                            <span className="text-sm text-muted-foreground">Destination:</span>
                                            <span className="font-medium text-green-600 dark:text-green-400">
                                                {servers.find(s => s.id === targetServerId)?.name}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {actionType === 'download' && (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <Button onClick={downloadFile} className="flex-1">
                                        <Download className="h-4 w-4 mr-2" />
                                        Download to Browser
                                    </Button>
                                    <Button
                                        onClick={() => setShowServerSelector(true)}
                                        disabled={!isFormValid}
                                        variant="outline"
                                        className="flex-1"
                                    >
                                        <Upload className="h-4 w-4 mr-2" />
                                        Transfer to Server
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Error Display */}
                    {showError && (
                        <div className="py-6 space-y-6">
                            <div className="text-center">
                                <div className="bg-destructive/10 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                                    <AlertCircle className="h-8 w-8 text-destructive" />
                                </div>
                                <h3 className="text-lg font-medium mb-2">Operation Failed</h3>
                                <p className="text-muted-foreground">Something went wrong during the operation</p>
                            </div>

                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>

                            <Button variant="outline" onClick={resetState} className="w-full">
                                <RefreshCw className="h-4 w-4 mr-2" />
                                Try Again
                            </Button>
                        </div>
                    )}
                </div>

                <DialogFooter className="border-t pt-4">
                    {showBrowser && (
                        <Button variant="outline" onClick={() => setShowBrowser(false)}>
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Form
                        </Button>
                    )}

                    {showServerSelector && (
                        <div className="flex gap-2 w-full">
                            <Button variant="outline" onClick={() => setShowServerSelector(false)} className="flex-1">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back
                            </Button>
                            <Button
                                onClick={startTransfer}
                                disabled={!isTransferFormValid}
                                className="flex-1"
                            >
                                <Upload className="h-4 w-4 mr-2" />
                                Start Transfer
                            </Button>
                        </div>
                    )}

                    {showForm && (
                        <div className="flex gap-2 w-full">
                            <Button variant="outline" onClick={handleClose} className="flex-1">
                                <X className="h-4 w-4 mr-2" />
                                Cancel
                            </Button>
                            <Button
                                onClick={startDownload}
                                disabled={!isFormValid}
                                className="flex-1"
                            >
                                <Download className="h-4 w-4 mr-2" />
                                Download
                            </Button>
                        </div>
                    )}

                    {(showProgress || showSuccess || showError) && (
                        <Button variant="outline" onClick={handleClose} className="w-full">
                            {showProgress ? (
                                <>
                                    <X className="h-4 w-4 mr-2" />
                                    Close (Operation Continues)
                                </>
                            ) : (
                                <>
                                    <X className="h-4 w-4 mr-2" />
                                    Close
                                </>
                            )}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default FileDownloadManager;
