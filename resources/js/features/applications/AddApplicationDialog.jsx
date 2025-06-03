import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { PlusCircleIcon } from "lucide-react";
import { DialogDescription } from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

export default function AddApplicationDialog({ serverId }) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        server_id: serverId,
        name: "",
        path: "",
        type: "",
        language: "",
        app_url: "",
        web_server: "",
        database_type: "",
        access_log_path: "",
        error_log_path: "",
    });

    const submit = (e) => {
        e.preventDefault();
        post(route("applications.store"), {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    const frameworkTypes = [
        { value: "laravel", label: "Laravel" },
        { value: "codeigniter", label: "CodeIgniter" },
        { value: "django", label: "Django" },
        { value: "flask", label: "Flask" },
        { value: "express", label: "Express.js" },
        { value: "nest", label: "Nest.js" },
        { value: "rails", label: "Ruby on Rails" },
        { value: "spring", label: "Spring Boot" },
        { value: "other", label: "Other" },
    ];

    const languages = [
        { value: "php", label: "PHP" },
        { value: "python", label: "Python" },
        { value: "javascript", label: "JavaScript" },
        { value: "typescript", label: "TypeScript" },
        { value: "ruby", label: "Ruby" },
        { value: "java", label: "Java" },
        { value: "go", label: "Go" },
        { value: "other", label: "Other" },
    ];

    const webServers = [
        { value: "nginx", label: "Nginx" },
        { value: "apache", label: "Apache" },
        { value: "caddy", label: "Caddy" },
        { value: "iis", label: "IIS" },
        { value: "other", label: "Other" },
    ];

    const databaseTypes = [
        { value: "mysql", label: "MySQL" },
        { value: "postgresql", label: "PostgreSQL" },
        { value: "mongodb", label: "MongoDB" },
        { value: "sqlite", label: "SQLite" },
        { value: "mariadb", label: "MariaDB" },
        { value: "sqlserver", label: "SQL Server" },
        { value: "other", label: "Other" },
    ];

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <PlusCircleIcon className="mr-2 h-4 w-4" /> Add Application
                </Button>
            </DialogTrigger>
            <DialogContent className="!sm:max-w-[425px] max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Add New Application</DialogTitle>
                </DialogHeader>
                <DialogDescription>
                    Provide the details of the new application
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 py-4">
                    {/* Application Name */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="name" className="text-right">
                            Name
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            className="col-span-3"
                            required
                        />
                        {errors.name && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    {/* Application Path */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="path" className="text-right">
                            Path
                        </Label>
                        <Input
                            id="path"
                            value={data.path}
                            onChange={(e) => setData("path", e.target.value)}
                            className="col-span-3"
                            placeholder="/var/www/your-app"
                            required
                        />
                        {errors.path && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.path}
                            </p>
                        )}
                    </div>

                    {/* Framework Type */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="type" className="text-right">
                            Framework
                        </Label>
                        <Select
                            onValueChange={(value) => setData("type", value)}
                            value={data.type}
                            required
                        >
                            <SelectTrigger className="col-span-3">
                                <SelectValue placeholder="Select framework" />
                            </SelectTrigger>
                            <SelectContent>
                                {frameworkTypes.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.type && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.type}
                            </p>
                        )}
                    </div>

                    {/* Programming Language */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="language" className="text-right">
                            Language
                        </Label>
                        <Select
                            onValueChange={(value) => setData("language", value)}
                            value={data.language}
                            required
                        >
                            <SelectTrigger className="col-span-3">
                                <SelectValue placeholder="Select language" />
                            </SelectTrigger>
                            <SelectContent>
                                {languages.map((lang) => (
                                    <SelectItem key={lang.value} value={lang.value}>
                                        {lang.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.language && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.language}
                            </p>
                        )}
                    </div>

                    {/* Application URL */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="app_url" className="text-right">
                            App URL
                        </Label>
                        <Input
                            id="app_url"
                            value={data.app_url}
                            onChange={(e) => setData("app_url", e.target.value)}
                            className="col-span-3"
                            placeholder="https://example.com"
                            required
                        />
                        {errors.app_url && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.app_url}
                            </p>
                        )}
                    </div>

                    {/* Web Server */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="web_server" className="text-right">
                            Web Server
                        </Label>
                        <Select
                            onValueChange={(value) => setData("web_server", value)}
                            value={data.web_server}
                        >
                            <SelectTrigger className="col-span-3">
                                <SelectValue placeholder="Select web server" />
                            </SelectTrigger>
                            <SelectContent>
                                {webServers.map((server) => (
                                    <SelectItem key={server.value} value={server.value}>
                                        {server.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.web_server && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.web_server}
                            </p>
                        )}
                    </div>

                    {/* Database Type */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="database_type" className="text-right">
                            Database
                        </Label>
                        <Select
                            onValueChange={(value) => setData("database_type", value)}
                            value={data.database_type}
                        >
                            <SelectTrigger className="col-span-3">
                                <SelectValue placeholder="Select database" />
                            </SelectTrigger>
                            <SelectContent>
                                {databaseTypes.map((db) => (
                                    <SelectItem key={db.value} value={db.value}>
                                        {db.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.database_type && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.database_type}
                            </p>
                        )}
                    </div>

                    {/* Access Log Path */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="access_log_path" className="text-right">
                            Access Log Path
                        </Label>
                        <Input
                            id="access_log_path"
                            value={data.access_log_path}
                            onChange={(e) => setData("access_log_path", e.target.value)}
                            className="col-span-3"
                            placeholder="/var/log/nginx/access.log"
                        />
                        {errors.access_log_path && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.access_log_path}
                            </p>
                        )}
                    </div>

                    {/* Error Log Path */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="error_log_path" className="text-right">
                            Error Log Path
                        </Label>
                        <Input
                            id="error_log_path"
                            value={data.error_log_path}
                            onChange={(e) => setData("error_log_path", e.target.value)}
                            className="col-span-3"
                            placeholder="/var/log/nginx/error.log"
                        />
                        {errors.error_log_path && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.error_log_path}
                            </p>
                        )}
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing ? "Adding..." : "Add Application"}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
