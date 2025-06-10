// utils/api.js - Helper functions for API calls

import {useState} from "react";

export const downloadAPI = {
    // Start a new download
    async startDownload(serverId, remotePath, localFilename) {
        const response = await fetch(`/downloads/servers/${serverId}/download`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                remote_path: remotePath,
                local_filename: localFilename
            })
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to start download');
        }

        return response.json();
    },

    // Get download progress
    async getProgress(progressKey) {
        const response = await fetch(`/downloads/progress/${progressKey}`);

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to get progress');
        }

        return response.json();
    },

    // Get download file URL
    getDownloadUrl(filename) {
        return `/api/downloads/file/${filename}`;
    },

    // Cancel download
    async cancelDownload(progressKey) {
        const response = await fetch(`/downloads/cancel/${progressKey}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to cancel download');
        }

        return response.json();
    }
};

// Example usage in a React component
// const ExampleUsage = () => {
//     const [downloads, setDownloads] = useState([]);
//
//     const handleStartDownload = async (serverId, remotePath, filename) => {
//         try {
//             const result = await downloadAPI.startDownload(serverId, remotePath, filename);
//             console.log('Download started:', result);
//
//             // Start polling for progress
//             const interval = setInterval(async () => {
//                 try {
//                     const progress = await downloadAPI.getProgress(result.progress_key);
//                     console.log('Progress:', progress);
//
//                     if (progress.status === 'complete' || progress.status === 'failed') {
//                         clearInterval(interval);
//                     }
//                 } catch (error) {
//                     console.error('Error getting progress:', error);
//                     clearInterval(interval);
//                 }
//             }, 1000);
//
//         } catch (error) {
//             console.error('Error starting download:', error);
//         }
//     };
//
//     return (
//         <div>
//             {/* Your component JSX */}
//         </div>
//     );
// };
