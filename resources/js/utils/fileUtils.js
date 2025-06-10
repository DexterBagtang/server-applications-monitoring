export const formatFileSize = (mb) => {
    const size = Number(mb);

    if (isNaN(size)) return "Invalid size";
    if (size < 1) return `${(size * 1024).toFixed(0)} KB`;
    if (size < 1024) return `${size.toFixed(2)} MB`;
    return `${(size / 1024).toFixed(2)} GB`;
};
