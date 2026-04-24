FROM node:20-alpine AS build

WORKDIR /app
COPY package.json ./
RUN npm install
COPY . .
RUN npm run build

FROM alpine:3.19
COPY --from=build /app/dist /build-output
# Clean destination first so stale hashed files don't linger and confuse browser cache
CMD ["sh", "-c", "find /app/dist -mindepth 1 -maxdepth 1 -exec rm -rf {} + 2>/dev/null; cp -r /build-output/. /app/dist/"]
