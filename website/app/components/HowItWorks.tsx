// Four studio steps with simple line icons. Wording stays generic on purpose:
// no film brands, warranty years or timings until the shop confirms them.

const icon = {
  inspect: (
    <>
      <circle cx="11" cy="11" r="6.5" />
      <path d="M16 16l4.5 4.5" />
      <path d="M8.5 11h5" />
    </>
  ),
  prep: (
    <>
      <path d="M12 3.5c3 3.6 5.5 6.6 5.5 9.6a5.5 5.5 0 0 1-11 0c0-3 2.5-6 5.5-9.6Z" />
      <path d="M9.5 13.5a2.6 2.6 0 0 0 2.5 2.5" />
    </>
  ),
  install: (
    <>
      <path d="M12 3l7 3v5.5c0 4.3-3 7.8-7 9.5-4-1.7-7-5.2-7-9.5V6l7-3Z" />
      <path d="M8.8 12.2l2.2 2.2 4.4-4.6" />
    </>
  ),
  handover: (
    <>
      <circle cx="8" cy="14" r="3.5" />
      <path d="M10.5 11.5L19 3M15.5 6.5l2.5 2.5M13.5 8.5l2 2" />
    </>
  ),
};

const steps: { key: keyof typeof icon; title: string; line: string }[] = [
  { key: "inspect", title: "Inspect", line: "We look over the paint and panels with you and agree what your car needs." },
  { key: "prep", title: "Prep", line: "Wash, decontaminate and polish, so the surface is clean before anything goes on." },
  { key: "install", title: "Install", line: "Film, coating or accessories, fitted by hand by our team in the studio." },
  { key: "handover", title: "Handover", line: "We walk round the car with you and explain the aftercare." },
];

export default function HowItWorks() {
  return (
    <ol className="steps-x">
      {steps.map((s, i) => (
        <li key={s.key} data-n={String(i + 1).padStart(2, "0")}>
          <span className="steps-x-icon" aria-hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
              {icon[s.key]}
            </svg>
          </span>
          <span className="steps-x-num" aria-hidden>{String(i + 1).padStart(2, "0")}</span>
          <h3>{s.title}</h3>
          <p>{s.line}</p>
        </li>
      ))}
    </ol>
  );
}
