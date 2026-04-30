const OpenAI = require('openai');

async function ask(apiKey, model, messages, systemPrompt) {
  const client = new OpenAI({ apiKey });

  const formatted = [
    { role: 'system', content: systemPrompt },
    ...messages.map(m => ({
      role: m.role,
      content: m.message || m.content
    }))
  ];

  const response = await client.chat.completions.create({
    model: model || 'gpt-4o-mini',
    messages: formatted,
    max_tokens: 1024
  });

  return {
    text: response.choices[0].message.content,
    tokens: response.usage ? response.usage.total_tokens : 0
  };
}

module.exports = { ask };
