// Positions are stored as whole seconds; the final stored second counts as the end.
export function resumePosition(position, duration) {
    if (!Number.isFinite(position) || position <= 0) return 0;
    if (Number.isFinite(duration) && duration > 0 && position >= Math.floor(duration)) return 0;
    return position;
}
