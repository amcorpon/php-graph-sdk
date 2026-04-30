const Groq = require('groq-sdk');

// Mapeia mimetype do WhatsApp para extensão aceita pelo Whisper
function resolveExt(mimetype) {
  const map = {
    'audio/ogg':          'ogg',
    'audio/ogg; codecs=opus': 'ogg',
    'audio/opus':         'ogg',
    'audio/mpeg':         'mp3',
    'audio/mp3':          'mp3',
    'audio/mp4':          'm4a',
    'audio/m4a':          'm4a',
    'audio/wav':          'wav',
    'audio/x-wav':        'wav',
    'audio/webm':         'webm',
    'audio/aac':          'm4a',
  };
  return map[mimetype?.split(';')[0]?.trim()] || 'ogg';
}

async function transcribeAudio(apiKey, audioBuffer, mimetype) {
  const client = new Groq({ apiKey });
  const ext    = resolveExt(mimetype);
  const mime   = mimetype?.split(';')[0]?.trim() || 'audio/ogg';

  const { toFile } = require('groq-sdk');

  const transcription = await client.audio.transcriptions.create({
    file:            await toFile(audioBuffer, `audio.${ext}`, { type: mime }),
    model:           'whisper-large-v3-turbo',
    language:        'pt',
    response_format: 'text',
  });

  return (typeof transcription === 'string' ? transcription : transcription?.text || '').trim();
}

module.exports = { transcribeAudio };
