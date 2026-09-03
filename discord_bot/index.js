/* BOT SECTION */
const eris = require('eris');

const bot = new eris.Client(process.env.DISCORD_BOT_TOKEN);

bot.on('ready', () => {
	console.log('ZCUTestBot ready!');
});

bot.on('error', err => {
	console.warn(err);
});

bot.connect();

/* API SECTION */
const express = require('express');
const app = express();
app.use(express.json())

app.post('/approved', (req, res) => {
	const baseUrl = "lumio.zcu.cz/akce/detail/"
	const id = req.body.id;
	const date = new Date(req.body.date);
	const name = req.body.name;

	// TODO: Send to the #events channel
	console.log(`Nová akce! <t:${date.getTime() / 1000}:f>: ${name} (${baseUrl + id})`);
	res.send('OK')
})

app.post('/suggested', (req, res) => {
	const date = new Date(req.body.date);
	const name = req.body.name;
	const organiser = req.body.organiser;

	// TODO: send to the #admin-events channel
	console.log(`:warning: Nová navrhnutá akce! <t:${date.getTime() / 1000}:f>: ${name} od ${organiser}`);
	res.send('OK')
})

const port = process.env.DISCORD_BOT_PORT
app.listen(port, () => {
	console.log(`Bot server listening at port ${port}`);
})