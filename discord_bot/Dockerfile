FROM node:24-alpine

ENV NODE_ENV=production
ENV DISCORD_BOT_PORT=4321

WORKDIR /app

COPY package*.json ./
RUN npm install --omit=dev

COPY index.js ./

EXPOSE 4321

CMD ["npm", "start"]
