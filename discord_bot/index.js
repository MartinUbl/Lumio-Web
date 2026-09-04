/* BOT SECTION */
const eris = require('eris');

const token = process.env.DISCORD_BOT_TOKEN;
if (!token) {
	console.error('DISCORD_BOT_TOKEN is not set.');
	process.exit(1);
}

const bot = new eris.Client(token);

bot.on('ready', () => {
	console.log('Lumio Discord bot ready!');
});

bot.on('error', err => {
	console.warn(err);
});

bot.connect();

/* API SECTION */
const express = require('express');
const app = express();
app.use(express.json())

app.get('/health', (req, res) => {
	res.send('OK');
})

app.post('/approved', async (req, res) => {
	const baseUrl = "https://lumio.zcu.cz/akce/detail/"
	const id = req.body.id;
	const date = new Date(req.body.date);
	const name = req.body.name;
	const message = `Nová akce! <t:${Math.floor(date.getTime() / 1000)}:f>: ${name} (${baseUrl + id})`;

	await sendMessage(process.env.DISCORD_EVENTS_CHANNEL_ID, message);
	res.send('OK')
})

app.post('/suggested', async (req, res) => {
	const date = new Date(req.body.date);
	const name = req.body.name;
	const organiser = req.body.organiser;
	const message = `:warning: Nová navrhnutá akce! <t:${Math.floor(date.getTime() / 1000)}:f>: ${name} od ${organiser}`;

	await sendMessage(process.env.DISCORD_ADMIN_EVENTS_CHANNEL_ID, message);
	res.send('OK')
})

async function sendMessage(channelId, message) {
	if (!channelId) {
		console.warn(`Discord channel is not configured, message not sent: ${message}`);
		return;
	}

	await bot.createMessage(channelId, message);
}

const port = Number(process.env.DISCORD_BOT_PORT || 4321)
app.listen(port, () => {
	console.log(`Bot server listening at port ${port}`);
})
