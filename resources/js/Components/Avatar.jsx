const colors = ['bg-[#dbeafe] text-[#2563eb]', 'bg-[#fce7f3] text-[#db2777]', 'bg-[#fef3c7] text-[#d97706]', 'bg-[#e9d5ff] text-[#7e22ce]', 'bg-[#dcfce7] text-[#16a34a]'];
export default function Avatar({ name = '?', index = 0, size = 'md' }) {
    const sizes = { sm: 'size-8 text-[11px]', md: 'size-11 text-sm', lg: 'size-14 text-lg' };
    const colorIndex = Array.from(String(name)).reduce((hash, character) => ((hash << 5) - hash + character.charCodeAt(0)) | 0, 0);
    return <span className={`${sizes[size]} ${colors[Math.abs(colorIndex || index) % colors.length]} flex shrink-0 items-center justify-center rounded-full border-2 border-white font-bold shadow-sm`}>{name.split(' ').map((word) => word[0]).slice(0, 2).join('').toUpperCase()}</span>;
}
