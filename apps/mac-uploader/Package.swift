// swift-tools-version: 6.0
import PackageDescription

let package = Package(
  name: "DanceProUploader",
  platforms: [.macOS(.v14)],
  products: [.executable(name: "DanceProUploader", targets: ["DanceProUploader"])],
  targets: [
    .target(name: "PreparationCore"),
    .target(name: "PhotoReviewCore", dependencies: ["PreparationCore"]),
    .executableTarget(
      name: "DanceProUploader", dependencies: ["PreparationCore", "PhotoReviewCore"]),
    .testTarget(
      name: "PreparationCoreTests",
      dependencies: ["PreparationCore", "PhotoReviewCore", "DanceProUploader"]),
  ]
)
