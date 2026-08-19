# --- SEO Copilot - Agency OS | Production Image ---
FROM nginx:alpine

# Nginx varsayılan içeriğini temizle
RUN rm -rf /usr/share/nginx/html/*

# Statik tek sayfa uygulamasını Nginx'in sunum dizinine kopyala
COPY . /usr/share/nginx/html/

# Nginx'in dinlediği portu dışa aç
EXPOSE 80

# nginx:alpine imajının varsayılan CMD'si (nginx -g 'daemon off;') zaten
# ön planda çalışacak şekilde ayarlıdır, ek bir komut gerekmez.
