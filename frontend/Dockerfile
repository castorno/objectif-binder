# syntax=docker/dockerfile:1

FROM node:24-alpine AS base
WORKDIR /app

# Dev image: source is bind-mounted by compose, this build only primes
# node_modules inside the image so the container can start standalone too.
FROM base AS dev
COPY package.json package-lock.json ./
RUN npm install
COPY . .
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
