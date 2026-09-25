suppressPackageStartupMessages(library(DESeq2))

args <- commandArgs(trailingOnly = TRUE)
if (length(args) != 6) stop("Expected matrix, cohort manifest, result, alpha, volcano plot, and sample-QC arguments.")

matrix_file <- args[[1]]
manifest_file <- args[[2]]
output_file <- args[[3]]
alpha <- as.numeric(args[[4]])
plot_file <- args[[5]]
sample_qc_file <- args[[6]]
lfc_threshold <- 1

matrix <- read.delim(matrix_file, check.names = FALSE, stringsAsFactors = FALSE)
manifest <- read.delim(manifest_file, check.names = FALSE, stringsAsFactors = FALSE)
id_columns <- c("sample_id", "sample_name", "sample_alias", "rnaseq_sample")

resolve_column <- function(row) {
  candidates <- unique(unlist(row[id_columns], use.names = FALSE))
  candidates <- candidates[!is.na(candidates) & nzchar(candidates)]
  matches <- candidates[candidates %in% colnames(matrix)]
  if (length(matches) == 0) return(NA_character_)
  matches[[1]]
}

manifest$matrix_column <- apply(manifest, 1, resolve_column)
manifest$matrix_status <- ifelse(is.na(manifest$matrix_column), "missing", "included")
write.table(manifest, sample_qc_file, sep = "\t", row.names = FALSE, quote = FALSE, na = "")
manifest <- manifest[manifest$matrix_status == "included", , drop = FALSE]
if (nrow(manifest) == 0) stop("None of the requested samples were found in the count matrix.")
if (anyDuplicated(manifest$matrix_column)) stop("A matrix column maps to more than one cohort row.")
if (!all(c("group_a", "group_b") %in% manifest$group)) stop("Both comparison groups are required.")
if (any(table(manifest$group) < 2)) stop("At least two count-matrix samples are required in each group after missing samples are ignored.")

sample_columns <- manifest$matrix_column
counts <- as.matrix(matrix[, sample_columns, drop = FALSE])
storage.mode(counts) <- "numeric"
if (any(!is.finite(counts)) || any(counts < 0)) stop("The count matrix contains missing, non-finite, or negative values.")
# Project count matrices contain abundance-estimator expected counts, which can
# be fractional. Match the existing Clinomics DESeq2 preprocessing by rounding
# those nonnegative expected counts immediately before dataset construction.
counts <- round(counts)

gene_column <- if ("gene_name" %in% colnames(matrix)) "gene_name" else if ("symbol" %in% colnames(matrix)) "symbol" else if ("gene_id" %in% colnames(matrix)) "gene_id" else colnames(matrix)[[1]]
genes <- as.character(matrix[[gene_column]])
valid <- !is.na(genes) & nzchar(genes)
counts <- counts[valid, , drop = FALSE]
genes <- genes[valid]
counts <- rowsum(counts, group = genes, reorder = FALSE)
counts <- counts[rowSums(counts) >= 10, , drop = FALSE]
if (nrow(counts) == 0) stop("No genes remain after count filtering.")

col_data <- data.frame(group = factor(manifest$group, levels = c("group_b", "group_a")))
rownames(col_data) <- sample_columns
colnames(counts) <- sample_columns
dds <- DESeqDataSetFromMatrix(countData = counts, colData = col_data, design = ~ group)
dds <- DESeq(dds, quiet = TRUE)
contrast <- c("group", "group_a", "group_b")
unshrunk <- results(
  dds,
  contrast = contrast,
  alpha = alpha,
  lfcThreshold = lfc_threshold,
  altHypothesis = "greaterAbs"
)
result <- as.data.frame(lfcShrink(dds, contrast = contrast, res = unshrunk, type = "normal", quiet = TRUE))
result$gene <- rownames(result)
result <- result[, c("gene", "baseMean", "log2FoldChange", "lfcSE", "stat", "pvalue", "padj")]
result <- result[order(result$padj, result$pvalue, na.last = TRUE), ]
write.table(result, output_file, sep = "\t", row.names = FALSE, quote = FALSE, na = "NA")

plot_data <- result[is.finite(result$log2FoldChange) & is.finite(result$padj) & result$padj > 0, , drop = FALSE]
plot_data$negative_log10_padj <- -log10(plot_data$padj)
finite_y <- is.finite(plot_data$negative_log10_padj)
if (any(!finite_y)) {
  plot_data$negative_log10_padj[!finite_y] <- max(plot_data$negative_log10_padj[finite_y], na.rm = TRUE) + 1
}
significant <- plot_data$padj <= alpha & abs(plot_data$log2FoldChange) >= lfc_threshold
colors <- ifelse(
  significant & plot_data$log2FoldChange > 0,
  "#D73027",
  ifelse(significant & plot_data$log2FoldChange < 0, "#2878B5", "#B8B8B8")
)
png(plot_file, width = 1800, height = 1400, res = 180)
par(mar = c(5.5, 6, 5, 2))
plot(
  plot_data$log2FoldChange,
  plot_data$negative_log10_padj,
  pch = 16,
  cex = 0.55,
  col = adjustcolor(colors, alpha.f = 0.65),
  xlab = "Shrunken log2 fold change (group A / group B)",
  ylab = expression(-log[10](adjusted~italic(P))),
  main = "Differential expression volcano plot"
)
abline(v = c(-lfc_threshold, lfc_threshold), h = -log10(alpha), lty = 2, col = "#555555")
label_indices <- head(order(plot_data$padj, na.last = NA), 12)
if (length(label_indices) > 0) {
  text(
    plot_data$log2FoldChange[label_indices],
    plot_data$negative_log10_padj[label_indices],
    labels = plot_data$gene[label_indices],
    pos = ifelse(plot_data$log2FoldChange[label_indices] >= 0, 4, 2),
    cex = 0.65,
    xpd = TRUE
  )
}
legend(
  "topright",
  legend = c("Higher in group A", "Higher in group B", "Other"),
  col = c("#D73027", "#2878B5", "#B8B8B8"),
  pch = 16,
  bty = "n",
  cex = 0.8
)
dev.off()
