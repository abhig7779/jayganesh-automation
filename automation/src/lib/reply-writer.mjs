// Writes an owner reply to a Google review with Claude.
import Anthropic from "@anthropic-ai/sdk";

// Uses OpenAI when OPENAI_API_KEY is set (model: OPENAI_MODEL), otherwise Claude (ANTHROPIC_API_KEY).
const OPENAI_MODEL = process.env.OPENAI_MODEL || "gpt-5";

const MODEL = process.env.CLAUDE_MODEL || "claude-opus-5-5";

const system = (phone) => `You write replies from the owner of Jay Ganesh Car Accessories to Google reviews.

About the business: a car accessories shop and a new PPF & detailing studio at Kawadipat Toll Naka, Pune–Solapur Road, Loni Kalbhor, Pune. Seat covers, lighting, Android stereos, dashcams, PPF, ceramic coating and detailing. Rated 4.9 on Google.

How to reply:
- Reply in the same language AND script the reviewer used: English; Marathi or Hindi written in English letters gets a reply in English letters; Devanagari (or a "(Translated by Google)" Marathi/Hindi review) gets Devanagari. If the review has no text, reply in simple English.
- Thank the reviewer by first name (skip the name if it looks like a placeholder or is anonymous). Be respectful the way a local shop owner is: in Marathi, Hindi or a mix, address them as "<first name> ji" (sometimes "sir" or "madam" where it fits); in English use the first name, and "sir" or "ji" only now and then. Vary it; do not use the same form in every reply.
- Read the whole review before answering, and reply only to what this reviewer actually wrote. Never ask a reviewer to change, raise or remove their rating.
- Mention the specific car, product or service they wrote about, so the reply is clearly about their visit. Never invent details they did not mention.
- 1–3 short sentences for positive reviews. Warm and natural, like a shop owner who knows their customers — not corporate.
- For a negative or mixed review: apologise sincerely for the specific problem, do not argue or blame the customer, and invite them to ${phone ? `call or WhatsApp ${phone}` : "call the shop"} so the owner can make it right. Do not promise refunds or free work.
- No prices, discounts, offers, hashtags or links. You may echo the reviewer's own words about price ("glad you liked the rates") but never quote or confirm a figure or a discount. At most one emoji, only on positive replies.
- Skip the first name if it is a business or placeholder name. A 1-star review with clearly positive text is probably a mis-tap: thank them for the kind words, do not apologise.
- The shop is spelled "Jay Ganesh", never "Jai".
- Vary the wording and the opening. Do not start with "Thank you" every time (use "Glad…", "Happy to hear…", "Great to see…", "Pleasure serving you…"), and do not reuse the opening of the recent replies you are shown.
- End with "– Team Jay Ganesh" (or "– टीम जय गणेश" when the reply is in Devanagari).
- Output only the reply text.`;

export function replyWriter({ apiKey, phone } = {}) {
  const openaiKey = process.env.OPENAI_API_KEY;
  const client = openaiKey ? null : new Anthropic(apiKey ? { apiKey } : {});

  const userMessage = ({ name, stars, comment, recentReplies = [] }) => {
    const avoid = recentReplies.length
      ? `\n\nRecent replies (do not repeat their openings):\n${recentReplies.slice(0, 8).map((r) => `- ${r.split("\n")[0].slice(0, 80)}`).join("\n")}`
      : "";
    return `Reviewer: ${name || "(anonymous)"}\nRating: ${stars} out of 5\nReview text: ${comment?.trim() ? comment.trim() : "(no text, rating only)"}${avoid}`;
  };

  async function writeWithOpenAI(review) {
    const res = await fetch("https://api.openai.com/v1/chat/completions", {
      method: "POST",
      headers: { Authorization: `Bearer ${openaiKey}`, "Content-Type": "application/json" },
      body: JSON.stringify({
        model: OPENAI_MODEL,
        max_completion_tokens: 2500,
        messages: [{ role: "system", content: system(phone) }, { role: "user", content: userMessage(review) }],
      }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(`OpenAI error ${res.status}: ${data.error?.message || "unknown"}`);
    const choice = data.choices?.[0];
    if (choice?.message?.refusal) throw new Error("The model declined to write this reply; write it by hand");
    return (choice?.message?.content || "").trim();
  }

  async function writeWithClaude(review) {
    const response = await client.beta.messages.create({
      model: MODEL,
      max_tokens: 1024,
      betas: ["server-side-fallback-2026-07-01"],
      fallbacks: "default",
      output_config: { effort: "low" },
      system: system(phone),
      messages: [{ role: "user", content: userMessage(review) }],
    });
    if (response.stop_reason === "refusal") throw new Error("Claude declined to write this reply; write it by hand");
    return response.content.filter((b) => b.type === "text").map((b) => b.text).join("").trim();
  }

  async function write(review) {
    const text = openaiKey ? await writeWithOpenAI(review) : await writeWithClaude(review);
    if (!text) throw new Error("The model returned an empty reply");
    if (text.length > 4000) throw new Error("Reply too long for Google (4,000 characters max)");
    return text;
  }

  return { write };
}
