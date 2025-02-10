# Use Laravel's Sail base image
FROM laravelsail/php82-composer:latest

# Install wkhtmltopdf and required fonts
RUN apt-get update && apt-get install -y \
    wkhtmltopdf \
    ttf-freefont \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
