import {useQuery} from "@tanstack/react-query";
import axios from "axios";

const fetchConnectionDetails = async (serverId) => {
    const {data} = await axios.get(`/api/servers/${serverId}/connection-details`)
    return data;
}

export default function useServerConnection(serverId, options = {}){
    return useQuery({
        queryKey:['serverConnection',serverId],
        queryFn: fetchConnectionDetails(serverId),
        staleTime: 5 * 60 * 1000,
        enabled: options.enabled,
    })
}
