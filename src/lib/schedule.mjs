// Post times in the calendar are India time (IST, UTC+05:30).
export function scheduledAt(post) {
  return new Date(`${post.date}T${post.time}:00+05:30`);
}

export function timing(post, now, graceHours) {
  const at = scheduledAt(post);
  const hoursLate = (now - at) / 36e5;
  if (hoursLate < 0) return { state: "upcoming", at };
  if (hoursLate <= graceHours) return { state: "due", at };
  return { state: "missed", at };
}

export const fmtIST = (d) =>
  d.toLocaleString("en-IN", { timeZone: "Asia/Kolkata", day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" });
