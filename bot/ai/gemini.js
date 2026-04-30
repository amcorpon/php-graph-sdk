const { GoogleGenerativeAI } = require('@google/generative-ai');

async function ask(apiKey, model, messages, systemPrompt) {
  const genAI = new GoogleGenerativeAI(apiKey);
  const genModel = genAI.getGenerativeModel({
    model: model || 'gemini-1.5-flash',
    systemInstruction: systemPrompt
  });

  const history = messages.slice(0, -1).map(m => ({
    role: m.role === 'assistant' ? 'model' : 'user',
    parts: [{ text: m.message || m.content }]
  }));

  const lastMessage = messages[messages.length - 1];
  const chat = genModel.startChat({ history });
  const result = await chat.sendMessage(lastMessage.message || lastMessage.content);
  const response = await result.response;

  return {
    text: response.text(),
    tokens: response.usageMetadata
      ? response.usageMetadata.promptTokenCount + response.usageMetadata.candidatesTokenCount
      : 0
  };
}

module.exports = { ask };
