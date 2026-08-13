export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex h-[3.1rem] items-center justify-center rounded-2xl border border-transparent bg-[#2ecc70] px-5 text-sm font-bold text-white shadow-[0_10px_20px_rgba(46,204,112,.20)] transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-[#2ecc70] focus:ring-offset-2 active:scale-[.98] ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
