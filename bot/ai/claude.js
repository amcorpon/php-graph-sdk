const Anthropic = require('@anthropic-ai/sdk');

async function ask(apiKey, model, messages, systemPrompt) {
  const client = new Anthropic({ apiKey });

  const response = await client.messages.create({
    model: model || 'claude-3-5-haiku-20241022',
    max_tokens: 1024,
    system: systemPrompt,
    messages: messages.map(m => ({
      role: m.role,
      content: m.message || m.content
    }))
  });

  return {
    text: response.content[0].text,
    tokens: response.usage.input_tokens + response.usage.output_tokens
  };
}

module.exports = { ask };
