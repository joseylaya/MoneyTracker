import { Component } from 'react';

export default class AppErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    render() {
        if (this.state.hasError) {
            return <main className="flex min-h-screen items-center justify-center bg-[#f6f8f7] p-6 text-center"><section className="max-w-sm rounded-[2rem] bg-white p-8 shadow-sm"><p className="eyebrow text-[#16b85b]">SplitShare</p><h1 className="mt-3 font-display text-2xl font-bold text-slate-900">This screen needs a refresh</h1><p className="mt-3 text-sm leading-6 text-slate-500">We could not finish loading this page. Your saved data has not been changed.</p><button className="ss-button mt-6 w-full" onClick={() => window.location.reload()}>Refresh page</button></section></main>;
        }

        return this.props.children;
    }
}
