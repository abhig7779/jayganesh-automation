// Writes an owner reply to a Google review with Claude.
import Anthropic from "@anthropic-ai/sdk";

const MODEL = process.env.CLAUDE_MODEL || "claude-opus-5-5";

const system = (phone) => `You write replies from the owner of Jay Ganesh Car Accessories to Google reviews.

About the business: a car accessories shop and a new PPF & detailing studio at Kawadipat Toll Naka, Pune–Solapur Road, Loni Kalbhor, Pune. Seat covers, lighting, Android stereos, dashcams, PPF, ceramic coating and detailing. Rated 4.9 on Google.

How to reply:
- Reply in the same language and script the reviewer used (English, Marathi, Hindi, or a Marathi/Hindi mix written in English letters). If the review has no text, reply in simple English.
- Thank the reviewer by first name (skip the name if it looks like a placeholder or is anonymous).
- Mention the specific car, product or service they wrote about, so the reply is clearly about their visit. Never invent details they did not mention.
- 1–3 short sentences for positive reviews. Warm and natural, like a shop owner who knows their customers — not corporate.
- For a negative or mixed review: apologise sincerely for the specific problem, do not argue or blame the customer, and invite them to ${phone ? `call or WhatsApp ${phone}` : "call the shop"} so the owner can make it right. Do not promise refunds or free work.
- No prices, no offers, no hashtags, no links. At most one emoji, only on positive replies.
- Vary the wording: do not reuse the opening of the recent replies you are shown.
- End with "– Team Jay Ganesh".
- Output only the reply text.`;

export function replyWriter({ apiKey, phone } = {}) {
  const client = new Anthropic(apiKey ? { apiKey } : {});

  async function write({ name, stars, comment, recentReplies = [] }) {
    const avoid = recentReplies.length
      ? `\n\nRecent replies (do not repeat their openings):\n${recentReplies.slice(0, 8).map((r) => `- ${r.split("\n")[0].slice(0, 80)}`).join("\n")}`
      : "";
    const response = await client.beta.messages.create({
      model: MODEL,
      max_tokens: 1024,
      betas: ["server-side-fallback-2026-07-01"],
      fallbacks: "default",
      output_config: { effort: "low" },
      system: system(phone),
      messages: [
        {
          role: "user",
          content: `Reviewer: ${name || "(anonymous)"}\nRating: ${stars} out of 5\nReview text: ${comment?.trim() ? comment.trim() : "(no text, rating only)"}${avoid}`,
        },
      ],
    });
    if (response.stop_reason === "refusal") throw new Error("Claude declined to write this reply; write it by hand");
    const text = response.content.filter((b) => b.type === "text").map((b) => b.text).join("").trim();
    if (!text) throw new Error("Claude returned an empty reply");
    if (text.length > 4000) throw new Error("Reply too long for Google (4,000 characters max)");
    return text;
  }

  return { write };
}
