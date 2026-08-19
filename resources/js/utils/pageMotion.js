let pendingMotion = null;
let lastDirection = 'forward';
let lastTarget = null;

export function setPageMotion(fromIndex, toIndex) {
    pendingMotion = { direction: toIndex < fromIndex ? 'backward' : 'forward', target: toIndex };
}

export function beginPageMotion() {
    const motion = pendingMotion || { direction: 'forward', target: null };
    lastDirection = motion.direction;
    lastTarget = motion.target;
    pendingMotion = null;
    return motion;
}

export function pageMotionDirection() {
    return lastDirection;
}

export function pageMotionTarget() {
    return lastTarget;
}
