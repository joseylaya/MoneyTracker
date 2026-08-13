import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'h-14 rounded-2xl border-0 bg-[#f5f7f8] px-4 text-base text-slate-800 shadow-none placeholder:text-slate-400 focus:ring-2 focus:ring-[#2ecc70] ' +
                className
            }
            ref={localRef}
        />
    );
});
