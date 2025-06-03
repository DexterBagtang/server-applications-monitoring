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
import {PlusCircleIcon} from "lucide-react";
import {DialogDescription} from "@/components/ui/dialog.js";

export default function AddServerDialog() {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        ip_address: "",
        is_active: true,
        auth_type: "password",
        username: "",
        password: "",
        port: 22,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route("servers.store"), {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline"><PlusCircleIcon /> Add New Server</Button>
            </DialogTrigger>
            <DialogContent className="!sm:max-w-[425px] max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Add New Server</DialogTitle>
                </DialogHeader>
                <DialogDescription>Provide the details of the new server</DialogDescription>
                <form onSubmit={submit} className="grid gap-4 py-4">
                    {/* Server Name */}
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
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    {/* IP Address */}
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
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.ip_address}
                            </p>
                        )}
                    </div>

                    {/* SSH Username */}
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
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.username}
                            </p>
                        )}
                    </div>

                    {/* SSH Password */}
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="password" className="text-right">
                            SSH Password
                        </Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData("password", e.target.value)}
                            className="col-span-3"
                        />
                        {errors.password && (
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    {/* SSH Port */}
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
                            <p className="col-span-4 text-right text-sm text-red-500">
                                {errors.port}
                            </p>
                        )}
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing ? "Adding..." : "Add Server"}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
