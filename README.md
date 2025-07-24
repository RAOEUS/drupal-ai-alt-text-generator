# Drupal AI Alt Text Generator Module

Provides AI-generated alt text suggestions for images without alt text in the media library, with a human review workflow. Supports both the cloud-based **OpenAI API** and a self-hosted **Ollama** instance.

-----

## Requirements

  * Drupal 10
  * PHP 7.4 or higher
  * PHP extensions: cURL, GD or Imagick
  * **An API Provider:**
      * **Option 1 (Cloud):** An OpenAI API key.
      * **Option 2 (Local):** A running [Ollama](https://ollama.com/) instance with a suitable vision model pulled (e.g., `ollama pull llava:13b`).

-----

## Installation

Installation is the same regardless of the chosen API provider.

### 1\. Via Composer (recommended)

1.  Require the module using Composer:
    ```bash
    composer require drupal/alt_text_review
    ```
2.  Enable the module and clear caches:
    ```bash
    drush en alt_text_review -y
    drush cr
    ```
3.  Assign the **Access Alt Text Review UI** permission to the appropriate roles.

### 2\. Manual Install

1.  Clone this repository into `web/modules/contrib/alt_text_review`.
2.  Enable the module and clear caches.
3.  Assign the **Access Alt Text Review UI** permission.

-----

## Configuration

Visit **Configuration » Media » Alt Text Review Configuration** (`/admin/config/media/alt-text-review`) and configure:

  * **API Provider**: Choose between **OpenAI** (cloud-based) or **Ollama** (self-hosted).
  * **OpenAI API key**: Required only if you select OpenAI.
  * **Ollama Hostname**: The address for your local Ollama instance (defaults to `http://localhost:11434`).
  * **Ollama Model**: The name of the installed vision model to use (e.g., `llava:13b`).
  * **AI Prompt**: A template for the AI. The token `[max_length]` is replaced by your max-length setting.
  * **Alt text maximum character length**: An integer value (default 128).
  * **Enable debug logging**: Logs full request/response payloads to the Drupal logs.

-----

## How It Works

1.  **Discovery**: Finds an image media entity with empty alt text.
2.  **Downscaling**: Resizes the image to fit within an 800×800 px box to reduce payload size.
3.  **Encoding**: The downscaled image is base64-encoded.
4.  **API Call**: The request is sent to your configured provider.
      * **OpenAI**: Sends a request to the Chat Completions API with the `gpt-4o-mini` model.
      * **Ollama**: Sends a request to your local `/api/generate` endpoint using your configured Ollama model.
5.  **Review & Save**: The form displays the AI suggestion for you to review, edit, and save.

-----

## Cost & Performance: OpenAI vs. Ollama

Your choice of provider involves a trade-off between pay-per-use convenience and the upfront/operational cost of self-hosting.

### OpenAI (`gpt-4o-mini`)

This is the simplest option, requiring no hardware management. You pay for what you use. The cost is very low, at approximately **$0.0026 per image**. Processing 10,000 images would cost about **$26**.

### Ollama (Self-Hosted)

This option is **free of API fees** but requires you to run the model on your own hardware. This is ideal for high-volume processing and enhances data privacy, as images never leave your infrastructure.

#### Recommended Vision Models & Hardware

The model you choose depends on the quality you need and the hardware you have available. A GPU is strongly recommended for all but the smallest models.

| Model Name        | Size (Disk) | Min. RAM/VRAM | Best For                                    |
| ----------------- | ----------- | ------------- | ------------------------------------------- |
| **`moondream`** | 1.6 GB      | 4 GB          | Fastest, low-resource, basic captions       |
| **`llava:7b`** | 4.1 GB      | 8 GB          | Good balance of quality and performance     |
| **`llava:13b`** | 7.4 GB      | 16 GB         | High-quality, detailed descriptions         |
| **`bakllava`** | 4.1 GB      | 8 GB          | An alternative to `llava:7b`, high performance |

To install a model, run the corresponding command in your terminal, for example: `ollama pull llava:13b`.

#### Cost Analysis

The main ongoing cost is electricity. For a mid-range GPU processing 10,000 images, the total electricity cost is typically **less than $1.00**, representing a significant savings over the OpenAI API at scale.

-----

## License

GPL-2.0
