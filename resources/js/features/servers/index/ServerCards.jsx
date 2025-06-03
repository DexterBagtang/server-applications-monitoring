import { Card } from "@/components/ui/card.js"
import { Badge } from "@/components/ui/badge.js"
import { CheckCircle, Clock, Server } from "lucide-react"
import ServerCard from "@/features/servers/index/ServerCard.jsx";

export function ServerCards({ servers , onServerSelect }) {

    return (
        <div>
            {servers.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {servers.map((server) => (
                        <ServerCard server={server} onServerSelect={onServerSelect} key={server.id} />
                    ))}
                </div>
            ) : (
                <div className="flex items-center justify-center h-24 text-center">
                    <p>No servers found</p>
                </div>
            )}
        </div>

    )
}
