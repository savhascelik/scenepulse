FROM node:22-alpine

WORKDIR /app

COPY package.json ./
COPY . .

ENV PORT=5173
ENV HOST=0.0.0.0
ENV BACKEND_INTERNAL_URL=http://backend:8000

EXPOSE 5173

CMD ["node", "server.js"]
