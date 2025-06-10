import { useState, useRef, useEffect } from 'react';
import { Loader2Icon, Terminal, AlertTriangle } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import axios from "axios";
import { Switch } from "@/components/ui/switch.js";
import { Label } from "@/components/ui/label.js";
import { toast } from "sonner";
import AnsiToHtml from 'ansi-to-html';
import {ScrollArea, ScrollBar} from "@/components/ui/scroll-area.js";

export function ServerTerminal({ server, directory = '~', label = 'Simple Terminal' }) {
    const [input, setInput] = useState('');
    const [output, setOutput] = useState([
        `Connected to ${server.name} (${server.hostname})`,
        `Current directory: ${directory}`
    ]);
    const [currentDir, setCurrentDir] = useState(directory);
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const [sudo, setSudo] = useState(false);

    const inputRef = useRef(null);
    const outputEndRef = useRef(null);
    const ansiConverter = new AnsiToHtml();

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!input.trim()) return;

        const command = input.trim();
        setOutput(prev => [...prev, ansiConverter.toHtml(`${sudo ? '🔥 ' : ''}$ ${command}`)]);
        setIsLoading(true);

        if (input.startsWith('cd ')) {
            const targetPath = input.slice(3).trim();
            const newPath = resolvePath(currentDir, targetPath);
            setCurrentDir(newPath);
            setIsLoading(false);
            setInput('');
            return
        }
        if (input === 'clear'){
            setOutput(['']);
            setIsLoading(false);
            setInput('');
            return;
        }

        const fullCommand = `cd ${currentDir} && ${sudo ? 'echo sudopassword | sudo -S' : ''} ${command}`;

        axios.post(`/servers/${server.id}/execute`, { command: fullCommand, sudoEnabled: sudo })
            .then(response => {
                const formattedOutput = ansiConverter.toHtml(response.data.output);
                setOutput(prev => [...prev, formattedOutput]);
            })
            .catch(error => {
                setOutput(prev => [...prev, ansiConverter.toHtml(`Error: ${error.response?.data?.output || 'Command failed'}`)]);
            })
            .finally(() => {
                setIsLoading(false);
                setInput('');
                setTimeout(() => inputRef.current?.focus(), 0);
            });



    };

    function resolvePath(current, inputPath) {
        if (!inputPath || inputPath === '') return current;

        let path;
        if (inputPath.startsWith('/')) {
            // Absolute path
            path = inputPath;
        } else {
            // Relative path
            path = current.endsWith('/') ? current + inputPath : current + '/' + inputPath;
        }

        // Normalize path like in Linux
        const parts = path.split('/');
        const stack = [];

        for (const part of parts) {
            if (part === '' || part === '.') continue;
            if (part === '..') {
                if (stack.length > 0) stack.pop();
            } else {
                stack.push(part);
            }
        }

        return '/' + stack.join('/');
    }

    useEffect(() => {
        if (isOpen) {
            outputEndRef.current?.scrollIntoView({ behavior: 'smooth' });
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    }, [output, isOpen]);

    function handleSudoChange(val) {
        setSudo(val)

        if (val) {
            toast.warning("You are now running commands with elevated privileges. Double-check each command before executing.", {
                position: "top-center",
                duration: 5000,
                icon: <AlertTriangle className="w-5 h-5 text-yellow-500" />,
                important: true,
                action: {
                    label: 'Got it',
                    onClick: () => {},
                },
            });
        } else {
            toast.success('Sudo disabled - commands will run with normal privileges');
        }
    }

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="gap-2">
                    <Terminal className="h-4 w-4" />
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="!max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Terminal className="h-5 w-5" />
                        Server Terminal - {server.name}
                    </DialogTitle>
                    <DialogDescription className="flex flex-col">
                        <span className="mb-1">{server.ip_address} | Current directory: {currentDir}</span>
                        <span className="text-xs text-blue-500 dark:text-blue-400">
                            Note: This is a simplified terminal for quick commands only.
                            For complex operations or interactive sessions, please use a full SSH client.
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <Card className={sudo ? "border-red-500" : ""}>
                    <CardHeader className="p-4 pb-2">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                <Switch
                                    id="sudo-mode"
                                    checked={sudo}
                                    onCheckedChange={handleSudoChange}
                                    className={sudo ? "data-[state=checked]:bg-red-500" : ""}
                                />
                                <Label htmlFor="sudo-mode" className={sudo ? "text-red-500 font-bold flex items-center gap-1" : ""}>
                                    {sudo && <AlertTriangle className="w-4 h-4" />}
                                    Run as sudo
                                </Label>
                            </div>
                            {sudo && (
                                <div className="flex items-center gap-2 bg-red-500/10 px-3 py-1 rounded-md">
                                    <AlertTriangle className="w-4 h-4 text-red-500" />
                                    <span className="text-red-500 text-sm font-medium">SUDO MODE ACTIVE</span>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="text-sm p-0">
                        <ScrollArea className={` font-mono p-4 rounded-b-md h-96 max-w-2xl mx-auto ${sudo ? "border-t border-red-500" : ""}`}>
                            {output.map((line, i) => (
                                <div key={i} style={{ whiteSpace: 'pre-wrap' }} dangerouslySetInnerHTML={{ __html: line }} />
                            ))}
                            <div ref={outputEndRef} />

                            <form onSubmit={handleSubmit} className="flex items-center mt-2">
                                <span className={`mr-2 ${sudo ? "text-red-400" : "text-green-400"}`}>
                                    {sudo ? (
                                        <span className="flex items-center">
                                            <AlertTriangle className="w-4 h-4 mr-1" />
                                            {currentDir} #
                                        </span>
                                    ) : (
                                        `${currentDir} $`
                                    )}
                                </span>
                                <input
                                    type="text"
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    ref={inputRef}
                                    className={`bg-transparent border-none flex-1 focus:outline-none ${sudo ? "text-red-400" : "text-green-400"}`}
                                    disabled={isLoading}
                                    autoFocus
                                />
                                {isLoading && (
                                    <Loader2Icon className={`w-4 h-4 animate-spin ${sudo ? "text-red-400" : "text-green-400"}`} />
                                )}
                            </form>
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                    </CardContent>
                </Card>
            </DialogContent>
        </Dialog>
    );
}
