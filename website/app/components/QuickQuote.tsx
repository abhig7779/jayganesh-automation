"use client";

import { useState } from "react";
import { popularCars } from "@/lib/catalog";
import { site, whatsapp } from "@/lib/site";
import { IconWhatsApp } from "./ui";

const needs = ["PPF", "Ceramic coating", "Detailing", "Seat covers", "Lights", "Android stereo", "Something else"];

export default function QuickQuote() {
  const [need, setNeed] = useState("PPF");
  const [car, setCar] = useState("");

  const message =
    need === "Something else"
      ? `Hi Jay Ganesh, I have a question about my ${car || "car"}: `
      : `Hi Jay Ganesh, I'd like a price for ${need.toLowerCase()} for my ${car || "car"}.`;

  return (
    <form
      className="quote"
      onSubmit={(e) => {
        e.preventDefault();
        window.open(whatsapp(message), "_blank", "noopener,noreferrer");
      }}
      aria-labelledby="quote-title"
    >
      <div className="quote-head">
        <p id="quote-title" className="quote-title">Get your price in 30 seconds</p>
        <p className="label">Reply on WhatsApp · {site.hoursShort}</p>
      </div>

      <fieldset className="quote-needs">
        <legend className="label">What do you need?</legend>
        <div className="quote-chips">
          {needs.map((n) => (
            <label key={n} className="quote-chip">
              <input type="radio" name="need" value={n} checked={need === n} onChange={() => setNeed(n)} />
              <span>{n}</span>
            </label>
          ))}
        </div>
      </fieldset>

      <label className="quote-car">
        <span className="label">Your car</span>
        <input
          id="quote-car"
          list="quote-cars"
          placeholder="e.g. Hyundai Creta 2024"
          value={car}
          onChange={(e) => setCar(e.target.value)}
          autoComplete="off"
        />
        <datalist id="quote-cars">
          {popularCars.map((c) => <option key={c} value={c} />)}
        </datalist>
      </label>

      <button type="submit" className="btn btn--primary btn--block" data-track="whatsapp">
        <IconWhatsApp />
        Get my price on WhatsApp
      </button>
    </form>
  );
}
