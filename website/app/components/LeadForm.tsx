"use client";

import "../styles/forms.css";
import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import { site, whatsapp } from "@/lib/site";
import {
  LEAD_ENDPOINT,
  normalizeIndianMobile,
  partnerTypeOptions,
  serviceOptions,
  vendorCategoryOptions,
  type LeadKind,
} from "@/lib/forms";
import { IconWhatsApp } from "./ui";

type Field = {
  name: string;
  label: string;
  type?: "text" | "tel" | "email" | "url" | "textarea" | "select";
  required?: boolean;
  options?: string[];
  placeholder?: string;
  autoComplete?: string;
  inputMode?: "text" | "tel" | "email" | "url";
  hint?: string;
  wide?: boolean;
};

const nameField: Field = { name: "name", label: "Your name", required: true, autoComplete: "name", placeholder: "Full name" };
const phoneField: Field = {
  name: "phone", label: "Mobile number", type: "tel", required: true, autoComplete: "tel", inputMode: "tel",
  placeholder: "98XXX XXXXX", hint: "10-digit mobile. We reply on WhatsApp.",
};
const emailField: Field = { name: "email", label: "Email", type: "email", autoComplete: "email", inputMode: "email", placeholder: "Optional" };
const cityField: Field = { name: "city", label: "Area or city", autoComplete: "address-level2", placeholder: "e.g. Hadapsar" };

const fieldsByKind: Record<LeadKind, Field[]> = {
  contact: [
    nameField, phoneField,
    { name: "car", label: "Car model", placeholder: "e.g. Hyundai Creta 2024" },
    { name: "service", label: "What do you need?", type: "select", options: serviceOptions },
    emailField, cityField,
    { name: "message", label: "Message", type: "textarea", required: true, wide: true, placeholder: "Tell us about your car and what you're looking for." },
  ],
  partnership: [
    nameField, phoneField,
    { name: "company", label: "Company or brand", required: true, autoComplete: "organization" },
    { name: "partnerType", label: "Type of partner", type: "select", options: partnerTypeOptions },
    { name: "volume", label: "Expected volume", placeholder: "e.g. cars per month" },
    cityField, emailField,
    { name: "message", label: "Message", type: "textarea", wide: true, placeholder: "What would you like to work on together?" },
  ],
  vendor: [
    nameField, phoneField,
    { name: "company", label: "Company name", required: true, autoComplete: "organization" },
    { name: "category", label: "Product category", type: "select", options: vendorCategoryOptions },
    { name: "brands", label: "Brands you supply", placeholder: "Brands or product lines" },
    { name: "website", label: "Website", type: "url", inputMode: "url", autoComplete: "url", placeholder: "https://" },
    { name: "gst", label: "GST number", placeholder: "Optional" },
    cityField, emailField,
    { name: "message", label: "Message", type: "textarea", wide: true, placeholder: "Products, pricing terms, delivery and warranty support." },
  ],
};

const defaults: Record<LeadKind, { title: string; intro: string; submit: string; waIntro: string }> = {
  contact: {
    title: "Send us a message",
    intro: "Tell us your car and what you need. We'll reply with options and a price.",
    submit: "Send message",
    waIntro: "Hi Jay Ganesh, I'd like to ask about my car.",
  },
  partnership: {
    title: "Tell us about your business",
    intro: "A few details are enough. We'll call to talk it through.",
    submit: "Send partnership enquiry",
    waIntro: "Hi Jay Ganesh, I'd like to talk about a partnership.",
  },
  vendor: {
    title: "Introduce your products",
    intro: "Share what you supply. We'll get back if it fits what our customers ask for.",
    submit: "Send vendor details",
    waIntro: "Hi Jay Ganesh, I'm a supplier and would like to introduce our products.",
  },
};

const requiredMsg: Record<string, string> = {
  name: "Please tell us your name.",
  phone: "Please enter your mobile number so we can reply.",
  company: "Please enter your company name.",
  message: "Please tell us a little about what you need.",
};

type Status = "idle" | "sending" | "success" | "error";

function validate(f: Field, raw: string): string | null {
  const v = raw.trim();
  if (!v) return f.required ? (requiredMsg[f.name] ?? `Please fill in ${f.label.toLowerCase()}.`) : null;
  if (f.name === "phone" && !normalizeIndianMobile(v)) return "Enter a 10-digit Indian mobile number, like 98XXX XXXXX.";
  if (f.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return "That email doesn't look right. Check it, or leave it empty.";
  if (f.type === "url" && !/^(https?:\/\/)?[^\s.]+\.[^\s]{2,}$/i.test(v)) return "That website doesn't look right. Check it, or leave it empty.";
  if (f.name === "name" && v.length < 2) return "Please enter your name.";
  return null;
}

export default function LeadForm({ kind, title, intro }: { kind: LeadKind; title?: string; intro?: string }) {
  const uid = useId();
  const fields = fieldsByKind[kind];
  const copy = defaults[kind];
  const mountedAt = useRef(Date.now());
  const formRef = useRef<HTMLFormElement>(null);
  const successRef = useRef<HTMLDivElement>(null);
  const [values, setValues] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<Status>("idle");
  const [serverError, setServerError] = useState<string | null>(null);
  const [honeypot, setHoneypot] = useState("");

  useEffect(() => { mountedAt.current = Date.now(); }, []);
  useEffect(() => { if (status === "success") successRef.current?.focus(); }, [status]);

  const id = (name: string) => `${uid}-${name}`;
  const firstName = (values.name ?? "").trim().split(/\s+/)[0] || "there";

  function waMessage() {
    const lines = [copy.waIntro];
    for (const f of fields) {
      const v = (values[f.name] ?? "").trim();
      if (v) lines.push(`${f.label}: ${v}`);
    }
    return lines.join("\n");
  }

  function setValue(name: string, v: string) {
    setValues((s) => ({ ...s, [name]: v }));
    // Clear an error as soon as the field becomes valid.
    if (errors[name]) {
      const f = fields.find((x) => x.name === name)!;
      const e = validate(f, v);
      setErrors((s) => {
        const n = { ...s };
        if (e) n[name] = e; else delete n[name];
        return n;
      });
    }
  }

  function onBlur(f: Field) {
    const v = values[f.name] ?? "";
    if (!v.trim()) return; // don't nag about empty fields until submit
    const e = validate(f, v);
    setErrors((s) => {
      const n = { ...s };
      if (e) n[f.name] = e; else delete n[f.name];
      return n;
    });
  }

  async function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (status === "sending") return;

    const next: Record<string, string> = {};
    for (const f of fields) {
      const err = validate(f, values[f.name] ?? "");
      if (err) next[f.name] = err;
    }
    setErrors(next);
    const firstBad = fields.find((f) => next[f.name]);
    if (firstBad) {
      formRef.current?.querySelector<HTMLElement>(`#${CSS.escape(id(firstBad.name))}`)?.focus();
      return;
    }

    const body = new URLSearchParams();
    body.set("form", kind);
    for (const f of fields) {
      let v = (values[f.name] ?? "").trim();
      if (f.name === "phone") v = normalizeIndianMobile(v) ?? v;
      body.set(f.name, v);
    }
    body.set("website_url", honeypot);
    body.set("t", String(Date.now() - mountedAt.current));
    body.set("page", typeof window !== "undefined" ? window.location.pathname : "");

    setStatus("sending");
    setServerError(null);
    try {
      // URLSearchParams body => application/x-www-form-urlencoded, a simple request (no preflight).
      const res = await fetch(LEAD_ENDPOINT, { method: "POST", body });
      let data: { ok?: boolean; error?: string } = {};
      try { data = await res.json(); } catch { /* not JSON */ }
      if (res.ok && data.ok) {
        setStatus("success");
      } else {
        setServerError(data.error || "We couldn't send your message just now.");
        setStatus("error");
      }
    } catch {
      setServerError("We couldn't reach our server. Check your connection, or send it on WhatsApp.");
      setStatus("error");
    }
  }

  if (status === "success") {
    return (
      <div className="lead-form lead-success" ref={successRef} tabIndex={-1} role="status" aria-live="polite">
        <span className="lead-success-mark" aria-hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
        </span>
        <h3>Thanks, {firstName}.</h3>
        <p>We&apos;ve got your message and will reply on WhatsApp or call within working hours.</p>
        <p className="label">{site.hoursShort}</p>
        <a className="btn btn--ghost" href={whatsapp(waMessage())} target="_blank" rel="noopener noreferrer" data-track="whatsapp">
          <IconWhatsApp />Need it sooner? WhatsApp us
        </a>
      </div>
    );
  }

  const sending = status === "sending";

  return (
    <form ref={formRef} className="lead-form" onSubmit={onSubmit} noValidate aria-labelledby={id("title")} data-kind={kind}>
      <div className="lead-form-head">
        <h3 id={id("title")}>{title ?? copy.title}</h3>
        <p>{intro ?? copy.intro}</p>
      </div>

      <div className="lead-grid">
        {fields.map((f) => {
          const fid = id(f.name);
          const err = errors[f.name];
          const describedBy = [f.hint && !err && `${fid}-hint`, err && `${fid}-err`].filter(Boolean).join(" ") || undefined;
          const common = {
            id: fid,
            name: f.name,
            value: values[f.name] ?? "",
            required: f.required,
            "aria-required": f.required || undefined,
            "aria-invalid": err ? true : undefined,
            "aria-describedby": describedBy,
            disabled: sending,
            onBlur: () => onBlur(f),
          } as const;
          return (
            <div key={f.name} className={["field", f.wide && "field--wide", err && "has-error"].filter(Boolean).join(" ")}>
              <label htmlFor={fid}>
                {f.label}
                {f.required ? <span className="field-req" aria-hidden> *</span> : <span className="field-opt"> (optional)</span>}
              </label>
              {f.type === "textarea" ? (
                <textarea {...common} rows={4} placeholder={f.placeholder} onChange={(e) => setValue(f.name, e.target.value)} />
              ) : f.type === "select" ? (
                <div className="select">
                  <select {...common} onChange={(e) => setValue(f.name, e.target.value)}>
                    <option value="">Choose one</option>
                    {f.options!.map((o) => <option key={o} value={o}>{o}</option>)}
                  </select>
                </div>
              ) : (
                <input
                  {...common}
                  type={f.type ?? "text"}
                  inputMode={f.inputMode}
                  autoComplete={f.autoComplete}
                  placeholder={f.placeholder}
                  onChange={(e) => setValue(f.name, e.target.value)}
                />
              )}
              {f.hint && !err && <p className="field-hint" id={`${fid}-hint`}>{f.hint}</p>}
              {err && <p className="field-error" id={`${fid}-err`}>{err}</p>}
            </div>
          );
        })}

        {/* Honeypot: people never see or fill this. */}
        <div className="lead-hp" aria-hidden="true">
          <label htmlFor={id("website_url")}>Leave this empty</label>
          <input
            id={id("website_url")}
            type="text"
            name="website_url"
            tabIndex={-1}
            autoComplete="off"
            value={honeypot}
            onChange={(e) => setHoneypot(e.target.value)}
          />
        </div>
      </div>

      {status === "error" && (
        <div className="lead-alert" role="alert">
          <p><strong>Your message didn&apos;t go through.</strong> {serverError} Your details are still here, so you can try again or send them on WhatsApp.</p>
        </div>
      )}

      <div className="lead-actions">
        <button className="btn btn--primary lead-submit" type="submit" disabled={sending} aria-busy={sending || undefined}>
          {sending && <span className="spinner" aria-hidden />}
          {sending ? "Sending…" : copy.submit}
        </button>
        <a className="btn btn--ghost" href={whatsapp(waMessage())} target="_blank" rel="noopener noreferrer" data-track="whatsapp">
          <IconWhatsApp />Send on WhatsApp instead
        </a>
      </div>
      <p className="lead-note">
        We use your details only to reply to this message. See our <a href="/privacy-policy/">privacy policy</a>.
      </p>
    </form>
  );
}
