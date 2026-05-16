import OpenAI from "openai";

const client = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY
});

export default async function handler(req, res) {
  res.setHeader("Access-Control-Allow-Origin", "https://aarvella.com");
  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type");

  if (req.method === "OPTIONS") return res.status(200).end();

  if (req.method !== "POST") {
    return res.status(405).json({ error: "Method not allowed" });
  }

  try {
    const { messages = [] } = req.body;

    const response = await client.responses.create({
      model: "gpt-5.4-mini",
      instructions: `
You are Aarvella's AI Stylist.

Help users choose the right hair, skin, makeup, or bridal service.
Ask one question at a time.
Recommend max 3 services.
Do not give medical diagnosis.
Collect name, phone, service, and preferred date/time.
When ready, guide them to book on WhatsApp.
`,
      input: messages
    });

    res.status(200).json({
      reply: response.output_text
    });
  } catch (error) {
    console.error(error);
    res.status(500).json({
      error: "AI assistant unavailable"
    });
  }
}