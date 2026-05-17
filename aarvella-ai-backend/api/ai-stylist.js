import OpenAI from "openai";

const client = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY
});

const allowedOrigins = [
  "https://aarvella.com",
  "https://www.aarvella.com"
];

export default async function handler(req, res) {
  const origin = req.headers.origin;

  if (allowedOrigins.includes(origin)) {
    res.setHeader("Access-Control-Allow-Origin", origin);
  }

  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type");

  if (req.method === "OPTIONS") {
    return res.status(200).end();
  }

  if (req.method !== "POST") {
    return res.status(405).json({ error: "Only POST allowed" });
  }

  try {
    const { messages = [] } = req.body;

    const response = await client.responses.create({
      model: "gpt-5.4-mini",
      instructions: `
You are Aarvella's AI Stylist and booking assistant.

Business:
Aarvella is a luxury hair, skin, makeup, and bridal studio.

Your job:
1. Help customers choose the right service.
2. Ask one question at a time.
3. Recommend maximum 3 services.
4. Do not diagnose medical conditions.
5. For serious skin/hair concerns, suggest an in-salon consultation.
6. Collect booking details:
   - name
   - phone
   - preferred service
   - preferred date/time
7. When booking details are complete, provide a short confirmation message.

Tone:
Warm, premium, concise, and helpful.

Available services:
Hair:
- Haircut and Styling
- Balayage
- Global Hair Color
- Highlights
- Keratin
- Hair Spa

Skin:
- Facial
- Cleanup
- Detan
- Bridal Glow Ritual

Makeup:
- Party Makeup
- Engagement Makeup
- Bridal Makeup
`,
      input: messages
    });

    return res.status(200).json({
      reply: response.output_text
    });
  } catch (error) {
    console.error("AI error:", error);
    return res.status(500).json({
      reply: "Sorry, our AI Stylist is unavailable right now. Please book directly on WhatsApp."
    });
  }
}
