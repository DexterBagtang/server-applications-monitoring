import { useState } from "react";
import { useForm } from "@inertiajs/react";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Trash2Icon } from "lucide-react";
import {Button} from "@/components/ui/button.js";
import {toast} from "sonner";

export default function DeleteApplicationDialog({ application, open, setOpen }) {

    const { delete: destroy, processing } = useForm();

    const handleDelete = () => {
        destroy(route("applications.destroy", application.id), {
            onSuccess: () => {
                setOpen(false)
                toast.success('App removed successfully!')
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            {/*<AlertDialogTrigger asChild>*/}
                {/*<Button variant="ghost" size="sm">*/}
                {/*    <Trash2Icon className="mr-2 h-4 w-4" /> Delete Application*/}
                {/*</Button>*/}
            {/*</AlertDialogTrigger>*/}
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This action cannot be undone. This will permanently delete the
                        application "{application.name}" and remove all associated data
                        from our servers.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleDelete}
                        disabled={processing}
                        className="bg-red-600 hover:bg-red-700 focus:ring-red-600"
                    >
                        {processing ? "Removing..." : "Remove Application"}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
