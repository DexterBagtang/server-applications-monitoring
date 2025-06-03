import { useState, useEffect } from "react";
import { useForm } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { DialogDescription } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import axios from 'axios';
import useServerConnection from "@/hooks/servers/useServerConnection.js";


export default function EditServerDialog({ isOpen, onClose, server }) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        name: server?.name || "",
        ip_address: server?.ip_address || "",
        is_active: server?.is_active !== undefined ? server.is_active : true,
        username: server?.username || "",
        password: "", // Don't pre-fill password for security
        port: server?.port || 22,
    });


    // Update form when server prop changes
    useEffect(() => {
        if (server && isOpen) {
            const fetchData = async () => {
                try {
                    const response = await axios.get(`/api/servers/${server.id}/connection-details`);
                    const agent = response.data;
                    // console.log(agent);
                    setData({
                        name: server.name || "",
                        ip_address: server.ip_address || "",
                        is_active: server.is_active !== undefined ? server.is_active : true,
                        username: agent.username || "",
                        password: "", // Never pre-fill password
                        port: agent.port || ""
                    });
                } catch (error) {
                    console.error("Failed to fetch connection details:", error);
                }
            };

            fetchData();
        }
    }, [server, isOpen]);


    const submit = (e) => {
        e.preventDefault();
        console.log(data)
        patch(route("servers.update", server.id), {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    if (!server) return null;

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent
                // data-aria-hidden={false}
                className="!sm:max-w-[500px] max-w-3xl"
                inert={!isOpen}
                onOpenAutoFocus={(e) => e.preventDefault()} // Prevent auto-focus if needed
                onCloseAutoFocus={(e) => e.preventDefault()}
                data-aria-hidden={false}
            >
                <DialogHeader>
                    <DialogTitle>Edit Server</DialogTitle>
                </DialogHeader>
                <DialogDescription>Update the server configuration details</DialogDescription>
                <form onSubmit={submit} className="grid gap-4 py-4" aria-hidden={false}>
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="name" className="text-right">
                            Server Name
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            className="col-span-3"
                        />
                        {errors.name && (
                            <p className="col-span-4 text-right text-sm text-destructive">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    {/*<div className="grid grid-cols-4 items-center gap-4">*/}
                    {/*    <Label htmlFor="hostname" className="text-right">*/}
                    {/*        Hostname*/}
                    {/*    </Label>*/}
                    {/*    <Input*/}
                    {/*        id="hostname"*/}
                    {/*        value={data.hostname}*/}
                    {/*        onChange={(e) => setData("hostname", e.target.value)}*/}
                    {/*        className="col-span-3"*/}
                    {/*    />*/}
                    {/*    {errors.hostname && (*/}
                    {/*        <p className="col-span-4 text-right text-sm text-destructive">*/}
                    {/*            {errors.hostname}*/}
                    {/*        </p>*/}
                    {/*    )}*/}
                    {/*</div>*/}

                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="ip_address" className="text-right">
                            IP Address
                        </Label>
                        <Input
                            id="ip_address"
                            value={data.ip_address}
                            onChange={(e) => setData("ip_address", e.target.value)}
                            className="col-span-3"
                        />
                        {errors.ip_address && (
                            <p className="col-span-4 text-right text-sm text-destructive">
                                {errors.ip_address}
                            </p>
                        )}
                    </div>

                    {/*<div className="grid grid-cols-4 items-center gap-4">*/}
                    {/*    <Label htmlFor="os_type" className="text-right">*/}
                    {/*        OS Type*/}
                    {/*    </Label>*/}
                    {/*    <Input*/}
                    {/*        id="os_type"*/}
                    {/*        value={data.os_type}*/}
                    {/*        onChange={(e) => setData("os_type", e.target.value)}*/}
                    {/*        className="col-span-3"*/}
                    {/*        placeholder="Linux, Windows, etc."*/}
                    {/*    />*/}
                    {/*    {errors.os_type && (*/}
                    {/*        <p className="col-span-4 text-right text-sm text-destructive">*/}
                    {/*            {errors.os_type}*/}
                    {/*        </p>*/}
                    {/*    )}*/}
                    {/*</div>*/}

                    {/*<div className="grid grid-cols-4 items-center gap-4">*/}
                    {/*    <Label htmlFor="os_version" className="text-right">*/}
                    {/*        OS Version*/}
                    {/*    </Label>*/}
                    {/*    <Input*/}
                    {/*        id="os_version"*/}
                    {/*        value={data.os_version}*/}
                    {/*        onChange={(e) => setData("os_version", e.target.value)}*/}
                    {/*        className="col-span-3"*/}
                    {/*        placeholder="Ubuntu 22.04, Windows Server 2022, etc."*/}
                    {/*    />*/}
                    {/*    {errors.os_version && (*/}
                    {/*        <p className="col-span-4 text-right text-sm text-destructive">*/}
                    {/*            {errors.os_version}*/}
                    {/*        </p>*/}
                    {/*    )}*/}
                    {/*</div>*/}

                    {/*<div className="grid grid-cols-4 items-center gap-4">*/}
                    {/*    <Label htmlFor="auth_type" className="text-right">*/}
                    {/*        Auth Type*/}
                    {/*    </Label>*/}
                    {/*    <Select*/}
                    {/*        value={data.auth_type}*/}
                    {/*        onValueChange={(value) => setData("auth_type", value)}*/}
                    {/*    >*/}
                    {/*        <SelectTrigger className="col-span-3">*/}
                    {/*            <SelectValue placeholder="Authentication Type" />*/}
                    {/*        </SelectTrigger>*/}
                    {/*        <SelectContent>*/}
                    {/*            <SelectItem value="password">Password</SelectItem>*/}
                    {/*            <SelectItem value="key">SSH Key</SelectItem>*/}
                    {/*        </SelectContent>*/}
                    {/*    </Select>*/}
                    {/*    {errors.auth_type && (*/}
                    {/*        <p className="col-span-4 text-right text-sm text-destructive">*/}
                    {/*            {errors.auth_type}*/}
                    {/*        </p>*/}
                    {/*    )}*/}
                    {/*</div>*/}

                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="username" className="text-right">
                            SSH Username
                        </Label>
                        <Input
                            id="username"
                            value={data.username}
                            onChange={(e) => setData("username", e.target.value)}
                            className="col-span-3"
                        />
                        {errors.username && (
                            <p className="col-span-4 text-right text-sm text-destructive">
                                {errors.username}
                            </p>
                        )}
                    </div>

                    {/*{data.auth_type === "password" && (*/}
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="password" className="text-right">
                                SSH Password
                            </Label>
                            <Input
                                id="password"
                                type="text"
                                value={data.password}
                                onChange={(e) => setData("password", e.target.value)}
                                placeholder="Leave blank to keep current password"
                                className="col-span-3"
                            />
                            {errors.password && (
                                <p className="col-span-4 text-right text-sm text-destructive">
                                    {errors.password}
                                </p>
                            )}
                        </div>
                    {/*)}*/}

                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="port" className="text-right">
                            SSH Port
                        </Label>
                        <Input
                            id="port"
                            type="number"
                            value={data.port}
                            onChange={(e) => setData("port", parseInt(e.target.value))}
                            className="col-span-3"
                        />
                        {errors.port && (
                            <p className="col-span-4 text-right text-sm text-destructive">
                                {errors.port}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="is_active" className="text-right">
                            Active
                        </Label>
                        <div className="flex items-center space-x-2 col-span-3">
                            <Switch
                                id="is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) => setData("is_active", checked)}
                            />
                            <Label htmlFor="is_active" className="cursor-pointer">
                                {data.is_active ? "Enabled" : "Disabled"}
                            </Label>
                        </div>
                    </div>

                    <div className="flex justify-end space-x-2 pt-2">
                        <Button variant="outline" type="button" onClick={handleClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? "Saving..." : "Save Changes"}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
