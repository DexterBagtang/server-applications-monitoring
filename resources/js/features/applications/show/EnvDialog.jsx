import { useState, useEffect } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger
} from "@/components/ui/dialog";
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Copy, Check, Search, Key, Eye, EyeOff, Loader2 } from "lucide-react";

export function EnvDialog({ application, open, setOpen, isFetching }) {
    const [envData, setEnvData] = useState({});
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [copiedKey, setCopiedKey] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [hiddenValues, setHiddenValues] = useState(new Set());

    useEffect(() => {
        if (open && application) {
            fetchEnvVariables();
        }
    }, [open, application]);

    const fetchEnvVariables = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await fetch(`/api/env-variables/${application.id}`);
            if (!response.ok) {
                throw new Error('Failed to fetch environment variables');
            }
            const data = await response.json();
            setEnvData(data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const copyToClipboard = async (text, key) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopiedKey(key);
            setTimeout(() => setCopiedKey(null), 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    };

    const toggleValueVisibility = (key) => {
        const newHidden = new Set(hiddenValues);
        if (newHidden.has(key)) {
            newHidden.delete(key);
        } else {
            newHidden.add(key);
        }
        setHiddenValues(newHidden);
    };

    const filteredEnvData = Object.entries(envData).filter(([key, value]) =>
        key.toLowerCase().includes(searchTerm.toLowerCase()) ||
        value.toLowerCase().includes(searchTerm.toLowerCase())
    );

    const getValueType = (key, value) => {
        if (key.includes('PASSWORD') || key.includes('SECRET') || key.includes('KEY')) {
            return 'secret';
        }
        if (key.includes('URL') || key.includes('HOST')) {
            return 'url';
        }
        if (key.includes('PORT') || key.includes('TIMEOUT')) {
            return 'number';
        }
        if (value === 'true' || value === 'false') {
            return 'boolean';
        }
        return 'string';
    };

    const isValueHidden = (key) => hiddenValues.has(key);
    const isSensitive = (key) => key.includes('PASSWORD') || key.includes('SECRET') || key.includes('KEY');

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button className="hidden" />
            </DialogTrigger>
            <DialogContent className="!max-w-5xl max-h-[90vh] w-full">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Key className="h-5 w-5" />
                        Environment Variables
                        {application && (
                            <Badge variant="secondary" className="ml-2">
                                {application.name || `App ${application.id}`}
                            </Badge>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        Manage and view environment variables for your application
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3">
                    {/* Search Bar */}
                    <div className="relative">
                        <Search className="absolute left-2 top-1/2 transform -translate-y-1/2 h-3 w-3 text-muted-foreground" />
                        <Input
                            placeholder="Search variables..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="pl-8 h-8 text-sm"
                        />
                    </div>

                    {/* Content Area */}
                    <div className="relative h-[65vh] w-full overflow-hidden">
                        <ScrollArea className="h-full w-full pr-2">
                            {loading && (
                                <div className="flex items-center justify-center h-20">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    <span className="ml-2 text-sm">Loading...</span>
                                </div>
                            )}

                            {error && (
                                <div className="border border-destructive rounded p-3 text-sm">
                                    <div className="text-destructive font-medium">Error loading variables</div>
                                    <p className="text-xs mt-1 text-muted-foreground">{error}</p>
                                    <Button onClick={fetchEnvVariables} variant="outline" size="sm" className="mt-2 h-7 text-xs">
                                        Retry
                                    </Button>
                                </div>
                            )}

                            {!loading && !error && filteredEnvData.length === 0 && (
                                <div className="text-center text-muted-foreground py-6 text-sm">
                                    {searchTerm ? 'No variables match your search.' : 'No variables found.'}
                                </div>
                            )}

                            {!loading && !error && filteredEnvData.length > 0 && (
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-1">
                                    {filteredEnvData.map(([key, value]) => {
                                        const valueType = getValueType(key, value);
                                        const sensitive = isSensitive(key);
                                        const hidden = isValueHidden(key);
                                        const displayValue = hidden ? '•'.repeat(Math.min(value.length, 20)) : value;

                                        return (
                                            <div key={key} className="border rounded-md p-2 hover:bg-muted/30 transition-colors">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-1">
                                                        <code className="font-mono text-xs font-medium bg-muted px-1.5 py-0.5 rounded text-foreground">
                                                            {key}
                                                        </code>
                                                        <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset ${
                                                            valueType === 'secret' ? 'bg-red-50 text-red-700 ring-red-600/20' :
                                                                valueType === 'url' ? 'bg-blue-50 text-blue-700 ring-blue-600/20' :
                                                                    valueType === 'number' ? 'bg-green-50 text-green-700 ring-green-600/20' :
                                                                        valueType === 'boolean' ? 'bg-purple-50 text-purple-700 ring-purple-600/20' :
                                                                            'bg-gray-50 text-gray-700 ring-gray-600/20'
                                                        }`}>
                                                            {valueType}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex-1 font-mono text-xs bg-background border rounded px-2 py-1 break-all text-muted-foreground">
                                                            {displayValue}
                                                        </div>
                                                        <div className="flex items-center gap-0.5 flex-shrink-0">
                                                            {sensitive && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => toggleValueVisibility(key)}
                                                                    className="h-6 w-6 p-0"
                                                                >
                                                                    {hidden ? <Eye className="h-3 w-3" /> : <EyeOff className="h-3 w-3" />}
                                                                </Button>
                                                            )}
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => copyToClipboard(value, `value-${key}`)}
                                                                className="h-6 w-6 p-0"
                                                                title="Copy value"
                                                            >
                                                                {copiedKey === `value-${key}` ? (
                                                                    <Check className="h-3 w-3 text-green-600" />
                                                                ) : (
                                                                    <Copy className="h-3 w-3" />
                                                                )}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
