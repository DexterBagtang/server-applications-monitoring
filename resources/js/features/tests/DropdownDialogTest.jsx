import React, { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function DropdownDialogTest() {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [selectedAction, setSelectedAction] = useState('');
    const [inputValue, setInputValue] = useState('');
    const [menuOpen, setMenuOpen] = useState(false)

    function handleMenuItemClick (action) {
        setMenuOpen(false);
        setSelectedAction(action);
        setIsDialogOpen(true);
    }

    const handleDialogClose = () => {
        setIsDialogOpen(false);
        setSelectedAction('');
        setInputValue('');
    };

    const handleSubmit = () => {
        console.log(`Action: ${selectedAction}, Input: ${inputValue}`);
        handleDialogClose();
    };

    function handleOpenModal(e) {
        setMenuOpen(false)
        setIsDialogOpen(true)
    }

    return (
        <div className="p-8 space-y-8">
            <div className="text-center">
                <h1 className="text-2xl font-bold mb-4">Shadcn Dropdown + Dialog Bug Test</h1>
                <p className="text-gray-600 mb-8">
                    Test the interaction between dropdown menu items that trigger dialogs.
                    After closing the dialog, check if the screen becomes unclickable.
                </p>
            </div>

            {/* Test Area */}
            <div className="flex flex-col items-center space-y-4">


                {/* Dropdown Menu */}
                <DropdownMenu open={menuOpen} onOpenChange={setMenuOpen}>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline">
                            Open Actions Menu test
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-56">
                        <DropdownMenuItem  onClick={(e) => {
                            handleMenuItemClick('create')
                        }
                        }
                        >
                            Create New Item
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={(e) => handleMenuItemClick('edit')}>
                            Edit Settings
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={(e) => handleMenuItemClick('delete')}>
                            Delete Item
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={(e) => handleMenuItemClick('share')}>
                            Share Item
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={(e)=> handleOpenModal(e)}>
                            Test
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Test Buttons to check if screen is clickable */}
                <div className="flex space-x-4">
                    <Button
                        variant="secondary"
                        onClick={() => alert('Button 1 clicked - screen is working!')}
                    >
                        Test Button 1
                    </Button>
                    <Button
                        variant="secondary"
                        onClick={() => alert('Button 2 clicked - screen is working!')}
                    >
                        Test Button 2
                    </Button>
                </div>

                {/* Additional clickable elements */}
                <div className="text-center space-y-2">
                    <p className="text-sm text-gray-500">Click these elements to test if the screen is responsive:</p>
                    <div className="flex space-x-4">
                        <button
                            className="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
                            onClick={() => alert('Blue button works!')}
                        >
                            Blue
                        </button>
                        <button
                            className="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600"
                            onClick={() => alert('Green button works!')}
                        >
                            Green
                        </button>
                        <button
                            className="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600"
                            onClick={() => alert('Red button works!')}
                        >
                            Red
                        </button>
                    </div>
                </div>
            </div>

            {/* Dialog */}
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>
                            {selectedAction === 'create' && 'Create New Item'}
                            {selectedAction === 'edit' && 'Edit Settings'}
                            {selectedAction === 'delete' && 'Delete Item'}
                            {selectedAction === 'share' && 'Share Item'}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedAction === 'create' && 'Enter details for the new item you want to create.'}
                            {selectedAction === 'edit' && 'Modify the settings as needed.'}
                            {selectedAction === 'delete' && 'Are you sure you want to delete this item? This action cannot be undone.'}
                            {selectedAction === 'share' && 'Configure sharing settings for this item.'}
                        </DialogDescription>
                    </DialogHeader>

                    {selectedAction !== 'delete' && (
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="input" className="text-right">
                                    {selectedAction === 'create' ? 'Name' :
                                        selectedAction === 'edit' ? 'Setting' : 'Email'}
                                </Label>
                                <Input
                                    id="input"
                                    value={inputValue}
                                    onChange={(e) => setInputValue(e.target.value)}
                                    className="col-span-3"
                                    placeholder={
                                        selectedAction === 'create' ? 'Enter item name...' :
                                            selectedAction === 'edit' ? 'Enter setting value...' :
                                                'Enter email address...'
                                    }
                                />
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={handleDialogClose}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSubmit}
                            variant={selectedAction === 'delete' ? 'destructive' : 'default'}
                        >
                            {selectedAction === 'create' && 'Create'}
                            {selectedAction === 'edit' && 'Save Changes'}
                            {selectedAction === 'delete' && 'Delete'}
                            {selectedAction === 'share' && 'Share'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Instructions */}
            <div className="mt-8 p-4 bg-gray-50 rounded-lg">
                <h3 className="font-semibold mb-2">Testing Instructions:</h3>
                <ol className="list-decimal list-inside space-y-1 text-sm">
                    <li>Click "Open Actions Menu" to open the dropdown</li>
                    <li>Select any menu item to trigger the dialog</li>
                    <li>Either cancel the dialog or submit the action</li>
                    <li>Try clicking the test buttons below to see if the screen is still clickable</li>
                    <li>If buttons don't respond, the bug is reproduced</li>
                </ol>
            </div>
        </div>
    );
}
